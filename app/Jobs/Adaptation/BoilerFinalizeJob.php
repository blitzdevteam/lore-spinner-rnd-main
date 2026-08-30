<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\BoilerFinalCutCanonicalizer;
use App\Ai\Adaptation\BoilerFinalCutValidator;
use App\Ai\Adaptation\BoilerFinalizeContextBuilder;
use App\Ai\Agents\Adaptation\BoilerFinalizeAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Exceptions\Adaptation\ArtifactValidationException;
use App\Exceptions\Adaptation\BoilerFinalizeNotClaimedException;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Services\Adaptation\BoilerCutNormalizer;
use App\Services\Adaptation\SessionEventLoader;
use App\Support\Adaptation\GeneratesValidatedArtifact;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class BoilerFinalizeJob implements ShouldQueue
{
    use Batchable, GeneratesValidatedArtifact, Queueable;

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
        if ($this->batch()?->cancelled()) {
            return;
        }

        $adaptation = $this->story->adaptation;
        $session = $adaptation?->sessionAdaptations()
            ->where('session_number', $this->sessionNumber)
            ->firstOrFail();

        if (! $adaptation?->boiler_enabled || ! $session) {
            return;
        }

        // Atomic claim: only one worker may run finalize at a time for this session.
        // Uses a SELECT FOR UPDATE lock so concurrent orphaned queue jobs stand down
        // rather than racing each other.
        $claim = DB::transaction(function () use ($session): string {
            $fresh = SessionAdaptation::lockForUpdate()->find($session->id);
            if ($fresh === null) {
                return 'missing';
            }
            if ($fresh->boiler_status === BoilerStatus::FINALIZED) {
                return 'already_finalized';
            }
            if ($fresh->boiler_status === BoilerStatus::FINALIZING) {
                // A killed or timed-out worker leaves FINALIZING forever. After the
                // job timeout there is no live owner, so this retry may reclaim.
                $staleBefore = now()->subSeconds($this->timeout);
                $hasCut = ! empty($fresh->boiler_final_cut);

                if ($hasCut || $fresh->updated_at === null || $fresh->updated_at->gt($staleBefore)) {
                    return 'held_by_another_worker';
                }

                Log::warning('boiler.finalize_reclaimed_stale_lock', [
                    'story_id' => $this->story->id,
                    'session_number' => $this->sessionNumber,
                    'held_since' => $fresh->updated_at?->toIso8601String(),
                ]);

                if (empty($fresh->boiler_draft)) {
                    return 'not_finalizable';
                }

                $fresh->update([
                    'boiler_status' => BoilerStatus::DRAFTED,
                    'boiler_error' => null,
                ]);
            }
            if ($fresh->boiler_status !== BoilerStatus::DRAFTED) {
                return 'not_finalizable';
            }
            $fresh->update([
                'boiler_status' => BoilerStatus::FINALIZING,
                'boiler_error' => null,
            ]);

            return 'claimed';
        });

        // The final cut already exists, so the rest of the chain has real artifacts
        // to work with and may proceed.
        if ($claim === 'already_finalized') {
            Log::info('boiler.finalize_already_finalized', [
                'story_id' => $this->story->id,
                'session_number' => $this->sessionNumber,
            ]);

            return;
        }

        // No final cut, and this job does not own the session. Returning normally here
        // would release the chained verification job against a null final cut, which
        // then reports missing artifacts as if they were editorial defects. Stop the
        // chain instead; the worker that holds the session drives its own chain, and a
        // retry of this job will observe FINALIZED and pass through cleanly.
        if ($claim !== 'claimed') {
            Log::warning('boiler.finalize_not_claimed', [
                'story_id' => $this->story->id,
                'session_number' => $this->sessionNumber,
                'reason' => $claim,
                'boiler_status' => $session->boiler_status,
            ]);

            throw new BoilerFinalizeNotClaimedException(
                "Boiler Finalize for session {$this->sessionNumber} did not claim the session ({$claim})."
            );
        }
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

            if ($throwable instanceof ArtifactValidationException) {
                $this->failTerminally($throwable);

                return;
            }

            throw $throwable;
        }
    }

    public function failed(?Throwable $throwable): void
    {
        // This job never owned the session, so it must not overwrite the status of
        // whichever worker does.
        if ($throwable instanceof BoilerFinalizeNotClaimedException) {
            return;
        }

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
        return $this->generateValidatedArtifact(
            artifact: 'finalize',
            originalPrompt: $originalPrompt,
            generate: fn (string $prompt): array => $canonicalizer->canonicalize(
                (new BoilerFinalizeAgent)->prompt($prompt)->toArray(),
                $session,
            ),
            validate: fn (array $result) => $validator->validate($result, $session, $events),
            repairPrompt: fn (string $error, array $previousCut): string => view(
                'ai.agents.adaptation.boiler-finalize.repair-prompt',
                [
                    'validationError' => $error,
                    'previousCut' => $previousCut,
                    'originalPrompt' => $originalPrompt,
                ],
            )->render(),
            logContext: [
                'story_id' => $this->story->id,
                'session_number' => $this->sessionNumber,
            ],
        );
    }
}
