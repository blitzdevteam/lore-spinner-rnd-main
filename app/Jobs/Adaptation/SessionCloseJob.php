<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\VoiceProfilePromptSlice;
use App\Ai\Agents\Adaptation\SessionCloseAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Event;
use App\Models\Story;
use App\Services\Adaptation\SessionEventLoader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Batchable;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

final class SessionCloseJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;
    public int $timeout = 420;
    public int $backoff = 60;

    public function __construct(
        private Story $story,
        private int $sessionNumber,
    ) {
        $this->onQueue('adaptation');
    }

    /** @throws Throwable */
    public function handle(SessionEventLoader $eventLoader): void
    {
        $adaptation = $this->story->adaptation;
        $session = $adaptation->sessionAdaptations()->where('session_number', $this->sessionNumber)->firstOrFail();

        if (empty($adaptation->voice_profile)) {
            throw new \RuntimeException(
                'voice_profile missing — Voice Lock must complete before Phase 7 (SessionClose)'
            );
        }

        try {
            $session->update(['session_status' => SessionAdaptationStatusEnum::SESSION_CLOSE]);

            $choiceDesign = $session->session_choice_design;
            $consequenceMap = $session->choice_consequence_map;

            // Use ip_trimming chapter segments for session-accurate source pages.
            $resolutionSourcePages = $this->story->getSessionTrimmedText($this->sessionNumber);

            // V2 shape: branching_choices[] is the canonical list; the
            // session-end hook is choice_number === 4 (the last one).
            // V1 fallback path: legacy `branching_choice_3` key.
            $branchingChoices = (array) ($choiceDesign['branching_choices'] ?? []);
            $sessionEndChoice = collect($branchingChoices)
                ->first(fn ($c) => ($c['category'] ?? null) === 'SESSION_END_HOOK'
                    || str_ends_with((string) ($c['choice_id'] ?? ''), '_C4'))
                ?? ($choiceDesign['branching_choice_3'] ?? null);

            $branchingConsequences = (array) ($consequenceMap['branching_consequences'] ?? []);
            $sessionEndConsequence = collect($branchingConsequences)
                ->first(fn ($c) => ($c['is_session_end_hook'] ?? false) === true)
                ?? ($consequenceMap['consequence_map_choice_3'] ?? null);

            $sessionAllocation = collect($adaptation->story_session_map['session_allocation'] ?? [])
                ->firstWhere('session_number', $this->sessionNumber);

            $sessionEvents = $eventLoader->forSession($this->story, $this->sessionNumber);

            if ($sessionEvents->isEmpty()) {
                throw new RuntimeException(
                    "No events found for story {$this->story->id} session {$this->sessionNumber}"
                );
            }

            $response = (new SessionCloseAgent((bool) $adaptation->boiler_enabled))->prompt(
                view('ai.agents.adaptation.session-close.prompt', [
                    'branchingChoice3Design' => $sessionEndChoice,
                    'choice3ConsequenceMap' => $sessionEndConsequence,
                    'sessionPrimaryGoal' => $sessionAllocation['primary_dramatic_question'] ?? '',
                    'sessionNumber' => $this->sessionNumber,
                    'sessionEvents' => $sessionEvents->map(fn (Event $ev) => [
                        'story_position' => $ev->story_position,
                        'title' => $ev->title,
                        'objectives' => $ev->objectives,
                    ])->all(),
                    'resolutionSourcePages' => $resolutionSourcePages,
                    'voiceProfile' => VoiceProfilePromptSlice::dnaAndBans((array) ($adaptation->voice_profile ?? [])),
                    'boilerDraft' => $adaptation->boiler_enabled ? $session->boiler_draft : null,
                ])->render()
            );

            $result = $response->toArray();

            // Resolve the LLM-selected story_position back to a concrete event_id, the same
            // way EntryPointDiagnosisJob resolves start_event_position -> start_event_id.
            // This is the exit-point counterpart to entry-point resolution: pipeline authors
            // an explicit integer, runtime reads it. No heuristics, no drift.
            $triggerPos = $result['session_close_trigger_event_position'] ?? null;
            $triggerEvent = $triggerPos !== null
                ? $sessionEvents->firstWhere('story_position', (int) $triggerPos)
                : null;

            if ($triggerEvent === null) {
                $triggerEvent = $sessionEvents->last();
            }

            $result['session_close_trigger_event_id'] = $triggerEvent->id;
            $result['session_close_trigger_event_position'] = $triggerEvent->story_position;

            if ($adaptation->boiler_enabled) {
                $this->validateBoilerClose($result, (array) $session->boiler_draft);
            }

            $session->update(['session_close_design' => $result]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }

    /**
     * @param  array<string, mixed>  $close
     * @param  array<string, mixed>  $draft
     */
    private function validateBoilerClose(array $close, array $draft): void
    {
        $moments = collect((array) ($draft['moment_drafts'] ?? []))->sortBy('moment_order')->values();
        $finalMoment = $moments->last();

        if (! is_array($finalMoment) || ($close['closing_moment_key'] ?? null) !== ($finalMoment['moment_key'] ?? null)) {
            throw new RuntimeException('Boiler Session Close must bind to the final ordered draft moment.');
        }

        if (! in_array((int) ($close['session_close_trigger_event_id'] ?? 0), (array) ($finalMoment['source_event_ids'] ?? []), true)) {
            throw new RuntimeException('Boiler session close trigger event must belong to the final ordered moment.');
        }

        foreach (['closing_moment_relationship', 'next_bridge_guidance'] as $field) {
            if (trim((string) ($close[$field] ?? '')) === '') {
                throw new RuntimeException("Boiler Session Close requires {$field}.");
            }
        }
    }
}
