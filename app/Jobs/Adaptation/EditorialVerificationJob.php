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

            // One automatic retry on RED, per Deliverable 6 ("One automatic retry
            // is permitted at the orchestration layer").
            if (
                data_get($verification, 'final_verdict.production_status') === 'RED'
                || ($adaptation->boiler_enabled && data_get($verification, 'boiler_audit.production_status') !== 'PASS')
            ) {
                $verification = $this->runVerification($adaptation, $session, $completeDesign);
                $verification['auto_retry_attempted'] = true;
                $session->update(['editorial_verification' => $verification]);
            }

            if ($adaptation->boiler_enabled && data_get($verification, 'boiler_audit.production_status') !== 'PASS') {
                $revisions = collect((array) data_get($verification, 'boiler_audit.revision_instructions', []))
                    ->filter(fn ($instruction): bool => is_string($instruction) && mb_trim($instruction) !== '')
                    ->values()
                    ->all();
                $failedChecks = collect((array) data_get($verification, 'boiler_audit', []))
                    ->filter(fn ($result, $key): bool => is_array($result)
                        && ($result['verdict'] ?? null) === 'REVISE'
                        && ! in_array($key, ['production_status', 'revision_instructions'], true))
                    ->map(fn (array $result, string $key): array => [
                        'check' => $key,
                        'detail' => (string) ($result['detail'] ?? ''),
                    ])
                    ->values()
                    ->all();
                $message = $this->boilerFailureMessage($revisions, $failedChecks);

                $session->update(['boiler_error' => mb_substr($message, 0, 2_000)]);
                Log::warning('boiler.editorial_verification_failed', [
                    'story_id' => $this->story->id,
                    'session_number' => $this->sessionNumber,
                    'production_status' => data_get($verification, 'boiler_audit.production_status'),
                    'revision_instructions' => $revisions,
                    'failed_checks' => $failedChecks,
                ]);

                throw new RuntimeException($message);
            }

            $session->update(['boiler_error' => null]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }

    /**
     * @param  array<string, mixed>  $completeDesign
     * @return array<string, mixed>
     */
    private function runVerification($adaptation, $session, array $completeDesign): array
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
                'boilerDraft' => $adaptation->boiler_enabled ? $session->boiler_draft : null,
                'boilerFinalCut' => $adaptation->boiler_enabled ? $session->boiler_final_cut : null,
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
