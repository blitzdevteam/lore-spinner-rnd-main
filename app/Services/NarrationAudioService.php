<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\Prompt;
use App\Models\Story;
use App\Services\Runtime\Mp3DurationCalculator;
use App\Services\Runtime\PlayerVoiceUsageRecorder;
use App\Tts\TtsVoiceCatalog;
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

    public function __construct(
        private readonly PlayerVoiceUsageRecorder $voiceUsage,
        private readonly ChaosEngineService $engine,
        private readonly Mp3DurationCalculator $duration,
    ) {}

    /**
     * Legacy cache path used for story-recommended voice renders (pre-selection).
     */
    public function legacyPath(Prompt $prompt): string
    {
        return "tts/{$this->provider()}/{$prompt->id}.mp3";
    }

    public function path(Prompt $prompt, string $voiceId): string
    {
        return "tts/{$this->provider()}/{$voiceId}/{$prompt->id}.mp3";
    }

    public function isReady(Prompt $prompt, string $voiceId, string $recommendedVoiceId): bool
    {
        if (Storage::disk('local')->exists($this->path($prompt, $voiceId))) {
            return true;
        }

        if ($voiceId === $recommendedVoiceId && Storage::disk('local')->exists($this->legacyPath($prompt))) {
            return true;
        }

        return false;
    }

    public function isGenerating(Prompt $prompt, string $voiceId, string $recommendedVoiceId): bool
    {
        return Cache::has($this->pendingKey((string) $prompt->id, $voiceId, $recommendedVoiceId));
    }

    public function failure(Prompt $prompt, string $voiceId, string $recommendedVoiceId): ?string
    {
        $message = Cache::get($this->errorKey((string) $prompt->id, $voiceId, $recommendedVoiceId));

        return is_string($message) ? $message : null;
    }

    public function pendingKey(string $promptId, string $voiceId, string $recommendedVoiceId): string
    {
        if ($voiceId === $recommendedVoiceId) {
            return "tts-pending:{$promptId}";
        }

        return "tts-pending:{$promptId}:{$voiceId}";
    }

    public function errorKey(string $promptId, string $voiceId, string $recommendedVoiceId): string
    {
        if ($voiceId === $recommendedVoiceId) {
            return "tts-error:{$promptId}";
        }

        return "tts-error:{$promptId}:{$voiceId}";
    }

    public function resolveStoragePath(Prompt $prompt, string $voiceId, string $recommendedVoiceId): string
    {
        $voicePath = $this->path($prompt, $voiceId);

        if (Storage::disk('local')->exists($voicePath)) {
            return $voicePath;
        }

        if ($voiceId === $recommendedVoiceId && Storage::disk('local')->exists($this->legacyPath($prompt))) {
            return $this->legacyPath($prompt);
        }

        return $voicePath;
    }

    /**
     * Flag a render as queued so concurrent player requests wait instead of
     * kicking off a second (billed) generation.
     */
    public function markPending(Prompt $prompt, string $voiceId, string $recommendedVoiceId): void
    {
        $promptId = (string) $prompt->id;

        Cache::put($this->pendingKey($promptId, $voiceId, $recommendedVoiceId), true, self::PENDING_SECONDS);
        Cache::forget($this->errorKey($promptId, $voiceId, $recommendedVoiceId));
    }

    /**
     * Queue the story-recommended voice render (used by turn/opening pipelines).
     */
    public function markPendingRecommended(Game $game, Prompt $prompt): void
    {
        $voiceId = $this->voiceIdFor($game);
        $this->markPending($prompt, $voiceId, $voiceId);
    }

    /**
     * Atomically reserve a browser-triggered render. Only the request that
     * creates the pending marker may schedule a post-response generation.
     */
    public function reserve(Prompt $prompt): bool
    {
        if ($this->isReady($prompt)) {
            return false;
        }

        $promptId = (string) $prompt->id;
        $reserved = Cache::add($this->pendingKey($promptId), true, self::PENDING_SECONDS);

        if ($reserved) {
            Cache::forget($this->errorKey($promptId));
        }

        return $reserved;
    }

    /**
     * Blocking render. Never call this from a request that a browser is
     * waiting on — the gateway will time out long before the provider answers.
     */
    public function generate(Game $game, Prompt $prompt, ?string $voiceId = null): void
    {
        $promptId = (string) $prompt->id;
        $voiceId = $voiceId ?? $this->voiceIdFor($game);
        $recommendedVoiceId = $this->voiceIdFor($game);
        $path = $this->storagePathForGeneration($prompt, $voiceId, $recommendedVoiceId);

        if (Storage::disk('local')->exists($path)) {
            Cache::forget($this->pendingKey($promptId, $voiceId, $recommendedVoiceId));

            return;
        }

        $lockKey = $voiceId === $recommendedVoiceId
            ? "tts-generate:{$promptId}"
            : "tts-generate:{$promptId}:{$voiceId}";

        $lock = Cache::lock($lockKey, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return;
        }

        Cache::put($this->pendingKey($promptId, $voiceId, $recommendedVoiceId), true, self::PENDING_SECONDS);

        // Runs post-response, so the request's execution budget no longer applies.
        set_time_limit(self::LOCK_SECONDS);

        try {
            if (Storage::disk('local')->exists($path)) {
                return;
            }

            $text = $this->engine->stripHtml((string) $prompt->response);

            if ($text === '') {
                throw new RuntimeException('Narration has no speakable text.');
            }

            $provider = $this->provider();
            $usageOccurredAt = now()->toImmutable()->utc();
            $providerStartedAt = microtime(true);
            $rendered = match ($provider) {
                'deepgram' => $this->renderDeepgram($text),
                'elevenlabs' => $this->renderElevenLabs($text, $voiceId),
                default => throw new RuntimeException("Unsupported TTS provider [{$provider}]."),
            };
            $latencyMs = (int) round((microtime(true) - $providerStartedAt) * 1_000);
            $audioDurationMs = $this->duration->milliseconds($rendered['audio']);

            try {
                $this->voiceUsage->record(
                    game: $game,
                    prompt: $prompt,
                    provider: $provider,
                    model: $rendered['model'],
                    voiceId: $rendered['voice_id'],
                    text: $text,
                    billableUnits: $rendered['billable_units'],
                    billingUnit: $rendered['billing_unit'],
                    audio: $rendered['audio'],
                    audioDurationMs: $audioDurationMs,
                    latencyMs: $latencyMs,
                    outputFormat: $rendered['output_format'],
                    providerRequestId: $rendered['request_id'],
                    providerTraceId: $rendered['trace_id'],
                    providerMetadata: $rendered['metadata'],
                    occurredAt: $usageOccurredAt,
                );
            } catch (Throwable $trackingError) {
                Log::error('tts.usage_record_failed', [
                    'prompt_id' => $promptId,
                    'game_id' => (string) $game->id,
                    'provider' => $provider,
                    'model' => $rendered['model'],
                    'exception' => $trackingError::class,
                    'message' => $trackingError->getMessage(),
                ]);
            }

            Storage::disk('local')->put($path, $rendered['audio']);
            Cache::forget($this->errorKey($promptId, $voiceId, $recommendedVoiceId));
        } catch (Throwable $e) {
            Cache::put(
                $this->errorKey($promptId, $voiceId, $recommendedVoiceId),
                self::FAILURE_MESSAGE,
                self::ERROR_SECONDS,
            );

            Log::warning('tts.generate_failed', [
                'prompt_id' => $promptId,
                'game_id' => (string) $game->id,
                'provider' => $this->provider(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        } finally {
            Cache::forget($this->pendingKey($promptId, $voiceId, $recommendedVoiceId));
            $lock->release();
        }
    }

    private function storagePathForGeneration(Prompt $prompt, string $voiceId, string $recommendedVoiceId): string
    {
        if ($voiceId === $recommendedVoiceId) {
            return $this->legacyPath($prompt);
        }

        return $this->path($prompt, $voiceId);
    }

    private function provider(): string
    {
        return $this->configString('services.tts_provider', 'elevenlabs');
    }

    private function voiceIdFor(Game $game): string
    {
        $slug = Story::query()->whereKey($game->story_id)->value('slug');

        return TtsVoiceCatalog::recommendedForSlug(is_string($slug) ? $slug : '');
    }

    /**
     * @return array{
     *     audio:string,
     *     model:string,
     *     voice_id:string,
     *     output_format:string,
     *     billable_units:int,
     *     billing_unit:string,
     *     request_id:?string,
     *     trace_id:?string,
     *     metadata:array<string, mixed>,
     * }
     */
    private function renderElevenLabs(string $text, string $voiceId): array
    {
        $apiKey = $this->configString('services.elevenlabs.api_key', '');
        $voiceId = $voiceId !== ''
            ? $voiceId
            : $this->configString('services.elevenlabs.voice_id', '');
        $model = $this->configString('services.elevenlabs.model_id', 'eleven_v3');

        if ($apiKey === '' || $voiceId === '') {
            throw new RuntimeException('ElevenLabs is not configured.');
        }

        $response = Http::withHeaders(['xi-api-key' => $apiKey])
            ->timeout(180)
            ->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}/stream?output_format=mp3_44100_128", [
                'text' => $text,
                'model_id' => $model,
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

        $characterCostHeader = $this->headerString($response->header('character-cost'));
        $characterCost = $this->positiveHeaderInteger(
            $characterCostHeader,
            mb_strlen($text),
        );

        return [
            'audio' => $response->body(),
            'model' => $model,
            'voice_id' => $voiceId,
            'output_format' => 'mp3_44100_128',
            'billable_units' => $characterCost,
            'billing_unit' => 'characters',
            'request_id' => $this->headerString($response->header('request-id')),
            'trace_id' => $this->headerString($response->header('x-trace-id')),
            'metadata' => [
                'usage_source' => $characterCostHeader === null
                    ? 'input_character_fallback'
                    : 'provider_header',
                'character_cost' => $characterCost,
                'content_type' => $this->headerString($response->header('content-type')),
                'http_status' => $response->status(),
            ],
        ];
    }

    /**
     * @return array{
     *     audio:string,
     *     model:string,
     *     voice_id:string,
     *     output_format:string,
     *     billable_units:int,
     *     billing_unit:string,
     *     request_id:?string,
     *     trace_id:null,
     *     metadata:array<string, mixed>,
     * }
     */
    private function renderDeepgram(string $text): array
    {
        $apiKey = $this->configString('services.deepgram.api_key', '');
        $voiceModel = $this->configString('services.deepgram.voice_model', 'aura-2-thalia-en');

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

        $model = $this->headerString($response->header('dg-model-name')) ?? $voiceModel;
        $characterCountHeader = $this->headerString($response->header('dg-char-count'));
        $characterCount = $this->positiveHeaderInteger(
            $characterCountHeader,
            mb_strlen($text),
        );

        return [
            'audio' => $response->body(),
            'model' => $model,
            'voice_id' => $voiceModel,
            'output_format' => 'mp3',
            'billable_units' => $characterCount,
            'billing_unit' => 'characters',
            'request_id' => $this->headerString($response->header('dg-request-id')),
            'trace_id' => null,
            'metadata' => [
                'usage_source' => $characterCountHeader === null
                    ? 'input_character_fallback'
                    : 'provider_header',
                'character_count' => $characterCount,
                'model_uuid' => $this->headerString($response->header('dg-model-uuid')),
                'content_type' => $this->headerString($response->header('content-type')),
                'http_status' => $response->status(),
            ],
        ];
    }

    private function positiveHeaderInteger(mixed $value, int $fallback): int
    {
        $value = $this->headerString($value);

        return is_numeric($value) ? max(0, (int) $value) : max(0, $fallback);
    }

    private function headerString(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return is_string($value) && mb_trim($value) !== '' ? mb_trim($value) : null;
    }

    private function configString(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_string($value) ? $value : $default;
    }
}
