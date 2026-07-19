<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\BoilerFinalCutCanonicalizer;
use App\Ai\Adaptation\BoilerFinalCutValidator;
use App\Ai\Adaptation\BoilerFinalizeContextBuilder;
use App\Ai\Agents\Adaptation\BoilerFinalizeAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Services\Adaptation\BoilerCutNormalizer;
use App\Services\Adaptation\SessionEventLoader;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class BoilerFinalizeJob implements ShouldQueue
{
    use Batchable, Queueable;

    private const int MAX_VALIDATION_ATTEMPTS = 2;

    public int $tries = 2;

    public int $timeout = 720;

    public int $backoff = 60;

    public function __construct(
        private Story $story,
        private int $sessionNumber,
    ) {
        $this->onQueue('adaptation');
    }

    /**
     * @throws Throwable
     */
    public function handle(
        BoilerFinalizeContextBuilder $contextBuilder,
        BoilerFinalCutCanonicalizer $canonicalizer,
        BoilerFinalCutValidator $validator,
        BoilerCutNormalizer $normalizer,
        SessionEventLoader $eventLoader,
    ): void {
        $adaptation = $this->story->adaptation;
        $session = $adaptation?->sessionAdaptations()
            ->where('session_number', $this->sessionNumber)
            ->firstOrFail();

        if (! $adaptation?->boiler_enabled || ! $session) {
            return;
        }

        if ($session->boiler_status === BoilerStatus::FINALIZED) {
            return;
        }

        if ($session->boiler_status === BoilerStatus::FAILED) {
            throw new RuntimeException(
                $session->boiler_error ?: "Boiler Finalize for session {$this->sessionNumber} is already failed."
            );
        }

        if (! in_array($session->boiler_status, [BoilerStatus::DRAFTED, BoilerStatus::FINALIZING], true)) {
            throw new RuntimeException('Boiler Finalize requires boiler_status drafted or finalizing.');
        }

        $session->update([
            'boiler_status' => BoilerStatus::FINALIZING,
            'boiler_error' => null,
        ]);
        Log::info('boiler.finalize_started', [
            'story_id' => $this->story->id,
            'session_number' => $this->sessionNumber,
        ]);

        try {
            $context = $contextBuilder->build($this->story, $this->sessionNumber);
            $events = $eventLoader->forSession($this->story, $this->sessionNumber);
            $originalPrompt = view('ai.agents.adaptation.boiler-finalize.prompt', [
                'context' => $context,
            ])->render();
            $result = $this->generateValidatedFinalCut(
                $originalPrompt,
                $canonicalizer,
                $validator,
                $session,
                $events,
            );
            $normalizer->normalize($session, $result);
            Log::info('boiler.finalize_completed', [
                'story_id' => $this->story->id,
                'session_number' => $this->sessionNumber,
                'moment_count' => count((array) ($result['moments'] ?? [])),
            ]);
        } catch (Throwable $throwable) {
            $session->update([
                'boiler_error' => mb_substr($throwable->getMessage(), 0, 2_000),
            ]);
            Log::warning('boiler.finalize_attempt_failed', [
                'story_id' => $this->story->id,
                'session_number' => $this->sessionNumber,
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }

    public function failed(?Throwable $throwable): void
    {
        $adaptation = $this->story->fresh()?->adaptation;
        $session = $adaptation?->sessionAdaptations()
            ->where('session_number', $this->sessionNumber)
            ->first();

        $session?->update([
            'boiler_status' => BoilerStatus::FAILED,
            'boiler_error' => mb_substr($throwable?->getMessage() ?? 'Boiler Finalize failed.', 0, 2_000),
            'session_status' => SessionAdaptationStatusEnum::FAILED,
        ]);
    }

    /**
     * @param  Collection<int, \App\Models\Event>  $events
     * @return array<string, mixed>
     */
    private function generateValidatedFinalCut(
        string $originalPrompt,
        BoilerFinalCutCanonicalizer $canonicalizer,
        BoilerFinalCutValidator $validator,
        SessionAdaptation $session,
        Collection $events,
    ): array {
        $prompt = $originalPrompt;
        $lastValidationError = null;

        for ($attempt = 1; $attempt <= self::MAX_VALIDATION_ATTEMPTS; $attempt++) {
            $result = (new BoilerFinalizeAgent)->prompt($prompt)->toArray();
            $result = $canonicalizer->canonicalize($result, $session);

            try {
                $validator->validate($result, $session, $events);

                if ($attempt > 1) {
                    Log::info('boiler.finalize_corrected', [
                        'story_id' => $this->story->id,
                        'session_number' => $this->sessionNumber,
                        'generation_attempt' => $attempt,
                    ]);
                }

                return $result;
            } catch (RuntimeException $validationError) {
                $lastValidationError = $validationError;

                Log::warning('boiler.finalize_validation_failed', [
                    'story_id' => $this->story->id,
                    'session_number' => $this->sessionNumber,
                    'generation_attempt' => $attempt,
                    'will_correct' => $attempt < self::MAX_VALIDATION_ATTEMPTS,
                    'error' => $validationError->getMessage(),
                ]);

                if ($attempt < self::MAX_VALIDATION_ATTEMPTS) {
                    $prompt = view('ai.agents.adaptation.boiler-finalize.repair-prompt', [
                        'validationError' => $validationError->getMessage(),
                        'previousCut' => $result,
                        'originalPrompt' => $originalPrompt,
                    ])->render();
                }
            }
        }

        throw $lastValidationError ?? new RuntimeException('Boiler Finalize validation failed without an error.');
    }
}
