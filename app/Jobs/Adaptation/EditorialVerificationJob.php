<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\EditorialVerificationAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Models\Story;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class EditorialVerificationJob implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Boiler audit items that judge craft rather than structure.
     *
     * A REVISE here means "this room could play richer", not "this session is
     * broken". They are surfaced for a human to act on; everything else — coverage,
     * bindings, alignment, budgets, arc, exit conditions — still hard-fails.
     */
    private const array ADVISORY_BOILER_CHECKS = [
        'playable_room_quality',
        'experience_balance',
    ];

    public int $tries = 1;

    public int $timeout = 600;

    public int $backoff = 60;

    public function __construct(
        private Story $story,
        private int $sessionNumber,
    ) {
        $this->onQueue('adaptation');
    }

    /** @throws Throwable */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $adaptation = $this->story->adaptation;
        $session = $adaptation->sessionAdaptations()->where('session_number', $this->sessionNumber)->firstOrFail();

        if (empty($adaptation->voice_profile)) {
            throw new RuntimeException(
                'voice_profile missing — Voice Lock must complete before Phase 8 (EditorialVerification)'
            );
        }

        // Without a finalized cut the Boiler audit has nothing to grade, and it reports
        // the absence as an editorial defect ("provide the Final Manifest..."). Fail
        // here instead, before spending a model call, so the real cause stays legible.
        if ($adaptation->boiler_enabled) {
            $missing = [];
            if ($session->boiler_status !== BoilerStatus::FINALIZED) {
                $missing[] = 'boiler_status is '.($session->boiler_status?->value ?? 'null').', expected finalized';
            }
            if (empty($session->boiler_final_cut)) {
                $missing[] = 'boiler_final_cut is empty';
            }
            if ($session->playableCut === null) {
                $missing[] = 'normalized playable cut is missing';
            }

            if ($missing !== []) {
                throw new RuntimeException(
                    "Boiler Editorial Verification cannot run for session {$this->sessionNumber}: "
                    .implode('; ', $missing).'. Boiler Finalize must complete first.'
                );
            }
        }

        try {
            $session->update(['session_status' => SessionAdaptationStatusEnum::EDITORIAL_VERIFICATION]);

            $completeDesign = [
                'entry_point_diagnosis' => $session->entry_point_diagnosis,
                'session_architecture' => $session->session_architecture,
                'session_choice_design' => $session->session_choice_design,
                'choice_consequence_map' => $session->choice_consequence_map,
                'session_close_design' => $session->session_close_design,
            ];

            $verification = $this->runVerification($adaptation, $session, $completeDesign);
            $session->update(['editorial_verification' => $verification]);

            $failedChecks = $this->failedBoilerChecks($verification);
            $blocking = $this->blockingChecks($failedChecks);

            // One automatic retry, per Deliverable 6 ("One automatic retry is permitted
            // at the orchestration layer"). The second pass is told exactly what the
            // first pass objected to; re-running the identical prompt would just be a
            // second roll of the same dice at full cost.
            if (data_get($verification, 'final_verdict.production_status') === 'RED' || $blocking !== []) {
                $verification = $this->runVerification(
                    $adaptation,
                    $session,
                    $completeDesign,
                    $this->revisionFocus($verification, $failedChecks),
                );
                $verification['auto_retry_attempted'] = true;
                $session->update(['editorial_verification' => $verification]);

                $failedChecks = $this->failedBoilerChecks($verification);
                $blocking = $this->blockingChecks($failedChecks);
            }

            if ($adaptation->boiler_enabled && $blocking !== []) {
                $revisions = $this->revisionInstructions($verification);
                $message = $this->boilerFailureMessage($revisions, $blocking);

                $session->update(['boiler_error' => mb_substr($message, 0, 2_000)]);
                Log::warning('boiler.editorial_verification_failed', [
                    'story_id' => $this->story->id,
                    'session_number' => $this->sessionNumber,
                    'production_status' => data_get($verification, 'boiler_audit.production_status'),
                    'revision_instructions' => $revisions,
                    'failed_checks' => $blocking,
                ]);

                throw new RuntimeException($message);
            }

            // Craft notes are editorial opinion about how rich a room feels. They are
            // recorded for a human to act on, but they must not brick a session whose
            // structure, bindings, and coverage are all sound.
            $advisory = $this->advisoryChecks($failedChecks);

            if ($advisory !== []) {
                Log::warning('boiler.editorial_verification_advisory', [
                    'story_id' => $this->story->id,
                    'session_number' => $this->sessionNumber,
                    'advisory_checks' => $advisory,
                ]);
            }

            $session->update(['boiler_error' => null]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }

    /**
     * Boiler audit items that came back REVISE, as check/detail pairs.
     *
     * @param  array<string, mixed>  $verification
     * @return array<int, array{check: string, detail: string}>
     */
    private function failedBoilerChecks(array $verification): array
    {
        return collect((array) data_get($verification, 'boiler_audit', []))
            ->filter(fn ($result, $key): bool => is_array($result)
                && ($result['verdict'] ?? null) === 'REVISE'
                && ! in_array($key, ['production_status', 'revision_instructions'], true))
            ->map(fn (array $result, string $key): array => [
                'check' => $key,
                'detail' => (string) ($result['detail'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * Failures that must stop the pipeline: structure, bindings, and coverage.
     *
     * @param  array<int, array{check: string, detail: string}>  $failedChecks
     * @return array<int, array{check: string, detail: string}>
     */
    private function blockingChecks(array $failedChecks): array
    {
        return array_values(array_filter(
            $failedChecks,
            fn (array $failure): bool => ! in_array($failure['check'], self::ADVISORY_BOILER_CHECKS, true),
        ));
    }

    /**
     * Failures that are craft feedback for a human rather than a broken session.
     *
     * @param  array<int, array{check: string, detail: string}>  $failedChecks
     * @return array<int, array{check: string, detail: string}>
     */
    private function advisoryChecks(array $failedChecks): array
    {
        return array_values(array_filter(
            $failedChecks,
            fn (array $failure): bool => in_array($failure['check'], self::ADVISORY_BOILER_CHECKS, true),
        ));
    }

    /**
     * @param  array<string, mixed>  $verification
     * @return array<int, string>
     */
    private function revisionInstructions(array $verification): array
    {
        return collect((array) data_get($verification, 'boiler_audit.revision_instructions', []))
            ->filter(fn ($instruction): bool => is_string($instruction) && mb_trim($instruction) !== '')
            ->values()
            ->all();
    }

    /**
     * The specific objections the retry pass must re-examine.
     *
     * @param  array<string, mixed>  $verification
     * @param  array<int, array{check: string, detail: string}>  $failedChecks
     * @return array<int, string>
     */
    private function revisionFocus(array $verification, array $failedChecks): array
    {
        $focus = array_map(
            static fn (array $failure): string => mb_trim("{$failure['check']}: {$failure['detail']}"),
            $failedChecks,
        );

        return array_values(array_unique([
            ...$focus,
            ...$this->revisionInstructions($verification),
        ]));
    }

    /**
     * @param  array<string, mixed>  $completeDesign
     * @param  array<int, string>  $priorRevisionFocus
     * @return array<string, mixed>
     */
    private function runVerification($adaptation, $session, array $completeDesign, array $priorRevisionFocus = []): array
    {
        // voice_decay_prevention_protocol is a runtime-only field (1B v2 Section 3B).
        // It must not reach Phase 8 editorial prompts — exclude it from the payload.
        $voiceProfileForEditorial = array_diff_key(
            (array) $adaptation->voice_profile,
            ['voice_decay_prevention_protocol' => true]
        );

        $response = (new EditorialVerificationAgent((bool) $adaptation->boiler_enabled))->prompt(
            view('ai.agents.adaptation.editorial-verification.prompt', [
                'completeSessionDesign' => $completeDesign,
                'storySessionMap' => $adaptation->story_session_map,
                'voiceProfile' => $voiceProfileForEditorial,
                'storyGuardCanon' => $adaptation->story_session_map['story_guard_canon'] ?? [],
                'persistentStateSchema' => $adaptation->story_session_map['persistent_state_schema'] ?? [],
                'worldReactivityRules' => $adaptation->story_session_map['world_reactivity_rules'] ?? [],
                'sessionNumber' => $this->sessionNumber,
                'boilerEnabled' => (bool) $adaptation->boiler_enabled,
                'priorRevisionFocus' => $priorRevisionFocus,
                'boilerDraft' => $adaptation->boiler_enabled ? $session->boiler_draft : null,
                // boiler_final_cut holds the manifest summary; the scene cards live in
                // boilerMoments below.
                'boilerManifest' => $adaptation->boiler_enabled ? $session->boiler_final_cut : null,
                'boilerEventRoles' => $adaptation->boiler_enabled
                    ? $session->playableCut?->eventRoles()->orderBy('event_id')->get()->toArray()
                    : [],
                'boilerMoments' => $adaptation->boiler_enabled
                    ? $session->playableCut?->moments()->orderBy('moment_order')->get()->toArray()
                    : [],
            ])->render()
        );

        return $response->toArray();
    }

    /**
     * @param  array<int, string>  $revisions
     * @param  array<int, array{check: string, detail: string}>  $failedChecks
     */
    private function boilerFailureMessage(array $revisions, array $failedChecks): string
    {
        $details = $revisions;

        if ($details === []) {
            $details = array_map(
                static fn (array $failure): string => "{$failure['check']}: {$failure['detail']}",
                $failedChecks,
            );
        }

        return 'Boiler Editorial Verification did not pass'
            .($details === [] ? '.' : ': '.implode(' | ', $details));
    }
}
