<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\BoilerDraftContextBuilder;
use App\Ai\Adaptation\BoilerDraftValidator;
use App\Ai\Agents\Adaptation\BoilerDraftAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Services\Adaptation\SessionEventLoader;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class BoilerDraftJob implements ShouldQueue
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
        BoilerDraftContextBuilder $contextBuilder,
        BoilerDraftValidator $validator,
        SessionEventLoader $eventLoader,
    ): void {
        $adaptation = $this->story->adaptation;
        $session = $adaptation?->sessionAdaptations()
            ->where('session_number', $this->sessionNumber)
            ->firstOrFail();

        if (! $adaptation?->boiler_enabled || ! $session) {
            return;
        }

        if (in_array($session->boiler_status, [BoilerStatus::DRAFTED, BoilerStatus::FINALIZED], true)) {
            return;
        }

        if ($session->boiler_status === BoilerStatus::FAILED) {
            throw new RuntimeException(
                $session->boiler_error ?: "Boiler Draft for session {$this->sessionNumber} is already failed."
            );
        }

        $session->update([
            'boiler_status' => BoilerStatus::DRAFTING,
            'boiler_error' => null,
        ]);
        Log::info('boiler.draft_started', [
            'story_id' => $this->story->id,
            'session_number' => $this->sessionNumber,
        ]);

        try {
            $context = $contextBuilder->build($this->story, $this->sessionNumber);
            $startPosition = isset($session->entry_point_diagnosis['start_event_position'])
                ? (int) $session->entry_point_diagnosis['start_event_position']
                : null;
            $events = $eventLoader->forSession($this->story, $this->sessionNumber, $startPosition);
            $originalPrompt = view('ai.agents.adaptation.boiler-draft.prompt', [
                'context' => $context,
            ])->render();
            $result = $this->generateValidatedDraft(
                $originalPrompt,
                $validator,
                $session,
                (array) $adaptation->story_session_map,
                $events,
            );

            $session->update([
                'boiler_draft' => $result,
                'boiler_status' => BoilerStatus::DRAFTED,
                'boiler_error' => null,
                'boiler_generated_at' => now(),
            ]);
            Log::info('boiler.draft_completed', [
                'story_id' => $this->story->id,
                'session_number' => $this->sessionNumber,
                'moment_count' => count((array) ($result['moment_drafts'] ?? [])),
                'event_count' => count((array) ($result['event_role_map'] ?? [])),
            ]);
        } catch (Throwable $throwable) {
            $session->update([
                'boiler_error' => mb_substr($throwable->getMessage(), 0, 2_000),
            ]);
            Log::warning('boiler.draft_attempt_failed', [
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
            'boiler_error' => mb_substr($throwable?->getMessage() ?? 'Boiler Draft failed.', 0, 2_000),
            'session_status' => SessionAdaptationStatusEnum::FAILED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $storyMap
     * @return array<string, mixed>
     */
    private function generateValidatedDraft(
        string $originalPrompt,
        BoilerDraftValidator $validator,
        SessionAdaptation $session,
        array $storyMap,
        Collection $events,
    ): array {
        $prompt = $originalPrompt;
        $previousDraft = [];
        $lastValidationError = null;

        for ($attempt = 1; $attempt <= self::MAX_VALIDATION_ATTEMPTS; $attempt++) {
            $result = (new BoilerDraftAgent)->prompt($prompt)->toArray();

            try {
                $validator->validate($result, $session, $storyMap, $events);

                if ($attempt > 1) {
                    Log::info('boiler.draft_corrected', [
                        'story_id' => $this->story->id,
                        'session_number' => $this->sessionNumber,
                        'generation_attempt' => $attempt,
                    ]);
                }

                return $result;
            } catch (RuntimeException $validationError) {
                $lastValidationError = $validationError;
                $previousDraft = $result;

                Log::warning('boiler.draft_validation_failed', [
                    'story_id' => $this->story->id,
                    'session_number' => $this->sessionNumber,
                    'generation_attempt' => $attempt,
                    'will_correct' => $attempt < self::MAX_VALIDATION_ATTEMPTS,
                    'error' => $validationError->getMessage(),
                ]);

                if ($attempt < self::MAX_VALIDATION_ATTEMPTS) {
                    $prompt = view('ai.agents.adaptation.boiler-draft.repair-prompt', [
                        'validationError' => $validationError->getMessage(),
                        'previousDraft' => $previousDraft,
                        'originalPrompt' => $originalPrompt,
                    ])->render();
                }
            }
        }

        throw $lastValidationError ?? new RuntimeException('Boiler Draft validation failed without an error.');
    }
}
