<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\EditorialVerificationAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Batchable;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

final class EditorialVerificationJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;
    public int $timeout = 300;
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
            throw new \RuntimeException(
                'voice_profile missing — Voice Lock must complete before Phase 8 (EditorialVerification)'
            );
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

            // One automatic retry on RED, per Deliverable 6 ("One automatic retry
            // is permitted at the orchestration layer").
            if (
                data_get($verification, 'final_verdict.production_status') === 'RED'
                || ($adaptation->boiler_enabled && data_get($verification, 'boiler_audit.production_status') !== 'PASS')
            ) {
                $verification = $this->runVerification($adaptation, $session, $completeDesign);
                $verification['auto_retry_attempted'] = true;
            }

            if ($adaptation->boiler_enabled && data_get($verification, 'boiler_audit.production_status') !== 'PASS') {
                throw new RuntimeException('Boiler Editorial Verification did not pass. Review boiler_audit revision instructions.');
            }

            $session->update(['editorial_verification' => $verification]);
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

        $response = (new EditorialVerificationAgent)->prompt(
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
}
