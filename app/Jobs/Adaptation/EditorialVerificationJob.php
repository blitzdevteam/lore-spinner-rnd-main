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
     * The Boiler audit is advisory in full. It reports, it never bricks a session.
     *
     * Everything it grades on the structural side — event coverage, choice and close
     * bindings, moment alignment, budgets, arc resolution, exit conditions — is already
     * proven deterministically by BoilerFinalCutValidator before a cut can reach
     * BoilerStatus::FINALIZED, and this job refuses to run without that status. Letting
     * a second, non-deterministic grader veto a machine-verified cut was double jeopardy:
     * a differing model opinion at temperature could destroy a correct session, and did.
     * The observed failure mode was an auditor demanding a consequence branch the source
     * material does not contain.
     *
     * So REVISE verdicts are recorded on the session for a human to read and are never
     * fatal. The full audit JSON lands on `editorial_verification`; a condensed punch
     * list lands on `boiler_error`, which `stories:boiler-status` already surfaces.
     * Genuine broken states still hard-fail: a missing voice profile, an unfinalized
     * cut, or an absent normalized cut all throw before a model call is spent.
     */
    private const string REVIEW_PREFIX = 'REVIEW (advisory, session not blocked): ';

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

            // A single pass. The former automatic retry existed to give a blocking
            // failure a second chance to clear; with the audit advisory there is nothing
            // to clear, so a second full-cost grading call would only produce a slightly
            // differently worded report.
            $verification = $this->runVerification($adaptation, $session, $completeDesign);
            $session->update(['editorial_verification' => $verification]);

            $failedChecks = $this->failedBoilerChecks($verification);

            if ($adaptation->boiler_enabled && $failedChecks !== []) {
                $revisions = $this->revisionInstructions($verification);

                $session->update([
                    'boiler_error' => mb_substr(
                        $this->reviewReport($revisions, $failedChecks),
                        0,
                        2_000,
                    ),
                ]);

                Log::warning('boiler.editorial_verification_advisory', [
                    'story_id' => $this->story->id,
                    'session_number' => $this->sessionNumber,
                    'production_status' => data_get($verification, 'boiler_audit.production_status'),
                    'final_verdict' => data_get($verification, 'final_verdict.production_status'),
                    'revision_instructions' => $revisions,
                    'failed_checks' => $failedChecks,
                ]);
            } else {
                $session->update(['boiler_error' => null]);
            }
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
     * A human-readable punch list for `stories:boiler-status`.
     *
     * Named checks come first so the reader can see at a glance which areas the
     * auditor questioned, then the auditor's own revision instructions.
     *
     * @param  array<int, string>  $revisions
     * @param  array<int, array{check: string, detail: string}>  $failedChecks
     */
    private function reviewReport(array $revisions, array $failedChecks): string
    {
        $checks = array_map(
            static fn (array $failure): string => $failure['check'],
            $failedChecks,
        );

        $report = self::REVIEW_PREFIX.implode(', ', array_unique($checks));

        if ($revisions !== []) {
            return $report.' | '.implode(' | ', $revisions);
        }

        $details = array_filter(array_map(
            static fn (array $failure): string => mb_trim("{$failure['check']}: {$failure['detail']}"),
            $failedChecks,
        ));

        return $details === [] ? $report : $report.' | '.implode(' | ', $details);
    }
}
