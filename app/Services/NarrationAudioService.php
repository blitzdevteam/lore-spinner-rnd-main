<?php

declare(strict_types=1);

namespace App\Services;

use App\ChaosMode\ChaosStoryConfig;
use App\Models\Game;
use App\Models\Prompt;
use App\Models\Story;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Renders narration audio for a prompt and caches the mp3 on disk.
 *
 * A full turn regularly takes ElevenLabs 30-90s, which is longer than the
 * nginx / Cloudflare gateway budget, so generate() must only ever run after the
 * HTTP response has been sent (deferred action or afterResponse callback).
 */
final class NarrationAudioService
{
    private const LOCK_SECONDS = 240;

    // Kept in step with the lock so a render killed mid-flight (worker recycled)
    // cannot leave the endpoint reporting "generating" forever.
    private const PENDING_SECONDS = 240;

    private const ERROR_SECONDS = 120;

    private const FAILURE_MESSAGE = 'Narration audio is unavailable for this turn.';

    public function path(Prompt $prompt): string
    {
        return "tts/{$this->provider()}/{$prompt->id}.mp3";
    }

    public function isReady(Prompt $prompt): bool
    {
        return Storage::disk('local')->exists($this->path($prompt));
    }

    public function isGenerating(Prompt $prompt): bool
    {
        return Cache::has($this->pendingKey((string) $prompt->id));
    }

    public function failure(Prompt $prompt): ?string
    {
        $message = Cache::get($this->errorKey((string) $prompt->id));

        return is_string($message) ? $message : null;
    }

    public function pendingKey(string $promptId): string
    {
        return "tts-pending:{$promptId}";
    }

    public function errorKey(string $promptId): string
    {
        return "tts-error:{$promptId}";
    }

    /**
     * Flag a render as queued so concurrent player requests wait instead of
     * kicking off a second (billed) generation.
     */
    public function markPending(Prompt $prompt): void
    {
        $promptId = (string) $prompt->id;

        Cache::put($this->pendingKey($promptId), true, self::PENDING_SECONDS);
        Cache::forget($this->errorKey($promptId));
    }

    /**
     * Blocking render. Never call this from a request that a browser is
     * waiting on — the gateway will time out long before the provider answers.
     */
    public function generate(Game $game, Prompt $prompt): void
    {
        $promptId = (string) $prompt->id;
        $path = $this->path($prompt);

        if (Storage::disk('local')->exists($path)) {
            Cache::forget($this->pendingKey($promptId));

            return;
        }

        $lock = Cache::lock("tts-generate:{$promptId}", self::LOCK_SECONDS);

        if (! $lock->get()) {
            return;
        }

        Cache::put($this->pendingKey($promptId), true, self::PENDING_SECONDS);

        // Runs post-response, so the request's execution budget no longer applies.
        set_time_limit(self::LOCK_SECONDS);

        try {
            $text = mb_trim(strip_tags((string) $prompt->response));

            if ($text === '') {
                throw new RuntimeException('Narration has no speakable text.');
            }

            $audio = $this->provider() === 'deepgram'
                ? $this->renderDeepgram($text)
                : $this->renderElevenLabs($text, $this->voiceIdFor($game));

            Storage::disk('local')->put($path, $audio);
            Cache::forget($this->errorKey($promptId));
        } catch (Throwable $e) {
            Cache::put($this->errorKey($promptId), self::FAILURE_MESSAGE, self::ERROR_SECONDS);

            Log::warning('tts.generate_failed', [
                'prompt_id' => $promptId,
                'game_id' => (string) $game->id,
                'provider' => $this->provider(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        } finally {
            Cache::forget($this->pendingKey($promptId));
            $lock->release();
        }
    }

    private function provider(): string
    {
        return (string) config('services.tts_provider', 'elevenlabs');
    }

    private function voiceIdFor(Game $game): string
    {
        $slug = (string) Story::query()->whereKey($game->story_id)->value('slug');

        return ChaosStoryConfig::ttsVoiceId($slug);
    }

    private function renderElevenLabs(string $text, string $voiceId): string
    {
        $apiKey = (string) config('services.elevenlabs.api_key');
        $voiceId = $voiceId !== '' ? $voiceId : (string) config('services.elevenlabs.voice_id');

        if ($apiKey === '' || $voiceId === '') {
            throw new RuntimeException('ElevenLabs is not configured.');
        }

        $response = Http::withHeaders(['xi-api-key' => $apiKey])
            ->timeout(180)
            ->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}/stream?output_format=mp3_44100_128", [
                'text' => $text,
                'model_id' => config('services.elevenlabs.model_id', 'eleven_v3'),
                'voice_settings' => [
                    'stability' => 0.50,
                    'similarity_boost' => 0.75,
                    'style' => 0.0,
                    'speed' => 1.0,
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("ElevenLabs responded {$response->status()}: ".mb_substr($response->body(), 0, 300));
        }

        return $response->body();
    }

    private function renderDeepgram(string $text): string
    {
        $apiKey = (string) config('services.deepgram.api_key');
        $voiceModel = (string) config('services.deepgram.voice_model', 'aura-2-thalia-en');

        if ($apiKey === '') {
            throw new RuntimeException('Deepgram is not configured.');
        }

        $response = Http::withHeaders([
            'Authorization' => "Token {$apiKey}",
            'Content-Type' => 'application/json',
        ])
            ->timeout(120)
            ->post("https://api.deepgram.com/v1/speak?model={$voiceModel}&encoding=mp3", [
                'text' => $text,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Deepgram responded {$response->status()}: ".mb_substr($response->body(), 0, 300));
        }

        return $response->body();
    }
}
