<?php

declare(strict_types=1);

namespace App\Http\Controllers\ChaosMode;

use App\ChaosMode\ChaosStoryConfig;
use App\Http\Controllers\Controller;
use App\Models\ChaosSession;
use App\Models\Story;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

final class ChaosTtsController extends Controller
{
    private const ELEVENLABS_URL = 'https://api.elevenlabs.io/v1/text-to-speech/%s/stream';

    private const LOCK_SECONDS = 180;

    private const LOCK_WAIT_SECONDS = 120;

    private const FAILURE_SECONDS = 120;

    /**
     * Serve ElevenLabs TTS audio for a narrator turn in a chaos session.
     *
     * Cache hit  → instant BinaryFileResponse from disk (full byte-range support).
     * Cache miss → one request acquires a shared lock, renders the file, and
     *              concurrent listeners wait to receive that same cached file.
     *
     * StreamedResponse was tried but browsers' <audio> elements always initiate
     * with a Range request (Range: bytes=0-). StreamedResponse has no Range
     * handling, causing nginx to return 416 Range Not Satisfiable. BinaryFileResponse
     * handles Accept-Ranges / Content-Range / 206 automatically and works on all
     * browsers including iOS Safari.
     *
     * eleven_v3 is the default model (set via ELEVENLABS_MODEL_ID in .env).
     * optimize_streaming_latency is not used — eleven_v3 returns 400 with it.
     */
    public function __invoke(ChaosSession $chaosSession, int $turnIndex): BinaryFileResponse
    {
        $history = $chaosSession->conversation_history ?? [];

        $narratorTurns = array_values(
            array_filter($history, fn (array $turn) => ($turn['role'] ?? '') === 'narrator')
        );

        abort_if(! isset($narratorTurns[$turnIndex]), 404, 'Turn not found.');

        $text = strip_tags((string) ($narratorTurns[$turnIndex]['text'] ?? ''));

        abort_if(blank($text), 404, 'Turn has no text content.');

        $path = "tts/chaos/{$chaosSession->id}/{$turnIndex}.mp3";

        $failureKey = $this->failureKey($chaosSession, $turnIndex);

        abort_if(Cache::has($failureKey), 503, 'Voice generation is temporarily unavailable.');

        if (! Storage::disk('local')->exists($path)) {
            $lock = Cache::lock($this->lockKey($chaosSession, $turnIndex), self::LOCK_SECONDS);
            $hasLock = false;

            try {
                $lock->block(self::LOCK_WAIT_SECONDS);
                $hasLock = true;

                if (Cache::has($failureKey)) {
                    abort(503, 'Voice generation is temporarily unavailable.');
                }

                if (! Storage::disk('local')->exists($path)) {
                    $slug = Story::find($chaosSession->story_id)?->slug ?? '';
                    $voiceId = ChaosStoryConfig::ttsVoiceId($slug);
                    $this->generate($text, $path, $voiceId);
                    Cache::forget($failureKey);
                }
            } catch (LockTimeoutException) {
                abort(503, 'Voice generation is still in progress. Please try again shortly.');
            } catch (Throwable $exception) {
                Cache::put($failureKey, true, self::FAILURE_SECONDS);

                throw $exception;
            } finally {
                if ($hasLock) {
                    $lock->release();
                }
            }
        }

        return $this->serve($path);
    }

    private function generate(string $text, string $path, string $voiceId): void
    {
        $apiKey  = config('services.elevenlabs.api_key');
        $voiceId = $voiceId !== '' ? $voiceId : (string) config('services.elevenlabs.voice_id');
        $modelId = config('services.elevenlabs.model_id', 'eleven_flash_v2_5');

        abort_unless(filled($apiKey) && filled($voiceId), 503, 'Voice generation is not configured.');

        $url = sprintf(self::ELEVENLABS_URL, $voiceId) . '?output_format=mp3_44100_128';

        $response = Http::withHeaders(['xi-api-key' => $apiKey])
            ->timeout(90)
            ->post($url, [
                'text'           => $text,
                'model_id'       => $modelId,
                'voice_settings' => [
                    'stability'        => 0.50,
                    'similarity_boost' => 0.75,
                    'style'            => 0.0,
                    'speed'            => 1.0,
                ],
            ]);

        if (! $response->successful()) {
            logger()->warning('ElevenLabs chaos TTS failed', [
                'status' => $response->status(),
                'path'   => $path,
            ]);

            abort($response->status() === 403 ? 502 : $response->status(), 'Voice generation unavailable.');
        }

        Storage::disk('local')->put($path, $response->body());
    }

    private function lockKey(ChaosSession $chaosSession, int $turnIndex): string
    {
        return "chaos-tts-generate:{$chaosSession->id}:{$turnIndex}";
    }

    private function failureKey(ChaosSession $chaosSession, int $turnIndex): string
    {
        return "chaos-tts-error:{$chaosSession->id}:{$turnIndex}";
    }

    /**
     * Serve a cached mp3 with full byte-range support.
     *
     * BinaryFileResponse handles Range / 206 / Content-Range automatically,
     * which is required for iOS Safari duration display and mid-track seeking.
     */
    private function serve(string $path): BinaryFileResponse
    {
        $response = new BinaryFileResponse(
            Storage::disk('local')->path($path),
            200,
            [
                'Content-Type'           => 'audio/mpeg',
                'Accept-Ranges'          => 'bytes',
                'Cache-Control'          => 'private, max-age=86400',
                'X-Content-Type-Options' => 'nosniff',
                'X-Accel-Buffering'      => 'no',
            ]
        );

        $response->setAutoLastModified();

        return $response;
    }
}
