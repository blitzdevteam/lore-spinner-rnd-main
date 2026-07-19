<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\VoiceProfilePromptSlice;
use App\Ai\Agents\Adaptation\ConsequenceMappingAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Batchable;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

final class ConsequenceMappingJob implements ShouldQueue
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
    public function handle(): void
    {
        $adaptation = $this->story->adaptation;
        $session = $adaptation->sessionAdaptations()->where('session_number', $this->sessionNumber)->firstOrFail();

        if (empty($adaptation->voice_profile)) {
            throw new \RuntimeException(
                'voice_profile missing — Voice Lock must complete before Phase 6 (ConsequenceMapping)'
            );
        }

        try {
            $session->update(['session_status' => SessionAdaptationStatusEnum::CONSEQUENCE_MAPPING]);

            $choiceDesign = $session->session_choice_design;

            $response = (new ConsequenceMappingAgent)->prompt(
                view('ai.agents.adaptation.consequence-mapping.prompt', [
                    // V2 shape: pass through the full branching_choices array
                    // (4 entries) plus the persistent state schema + reactivity
                    // rules so the consequence map can name specific NPCs / flags.
                    'branchingChoices' => (array) ($choiceDesign['branching_choices'] ?? []),
                    'emotionalChoices' => (array) ($choiceDesign['emotional_choices'] ?? []),
                    'storySessionMap' => $adaptation->story_session_map,
                    'persistentStateSchema' => $adaptation->story_session_map['persistent_state_schema'] ?? [],
                    'worldReactivityRules' => $adaptation->story_session_map['world_reactivity_rules'] ?? [],
                    'protagonistCoreTrait' => $adaptation->ip_audit['bounded_agency']['evidence'] ?? '',
                    'sessionNumber' => $this->sessionNumber,
                    'voiceProfile' => VoiceProfilePromptSlice::dnaAndBans((array) ($adaptation->voice_profile ?? [])),
                    'boilerDraft' => $adaptation->boiler_enabled ? $session->boiler_draft : null,
                ])->render()
            );

            $consequenceMap = $response->toArray();

            if ($adaptation->boiler_enabled) {
                $this->validateBoilerBindings(
                    $consequenceMap,
                    (array) $choiceDesign,
                    (array) $session->boiler_draft,
                );
            }

            $session->update(['choice_consequence_map' => $consequenceMap]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }

    /**
     * @param  array<string, mixed>  $consequenceMap
     * @param  array<string, mixed>  $choiceDesign
     * @param  array<string, mixed>  $draft
     */
    private function validateBoilerBindings(
        array $consequenceMap,
        array $choiceDesign,
        array $draft,
    ): void {
        $momentKeys = collect((array) ($draft['moment_drafts'] ?? []))
            ->pluck('moment_key')
            ->filter()
            ->values()
            ->all();
        $choices = collect([
            ...(array) ($choiceDesign['branching_choices'] ?? []),
            ...(array) ($choiceDesign['emotional_choices'] ?? []),
        ])->keyBy('choice_id');

        foreach (['branching_consequences', 'emotional_consequences'] as $collection) {
            foreach ((array) ($consequenceMap[$collection] ?? []) as $consequence) {
                if (! is_array($consequence)) {
                    throw new RuntimeException("Invalid Boiler {$collection} entry.");
                }

                $choice = $choices->get($consequence['choice_id'] ?? '');
                $momentKey = $consequence['moment_key'] ?? null;
                if (
                    ! is_array($choice)
                    || ! in_array($momentKey, $momentKeys, true)
                    || $momentKey !== ($choice['moment_key'] ?? null)
                ) {
                    throw new RuntimeException("Boiler {$collection} must bind to its authored choice moment.");
                }

                $echoMomentKey = $consequence['echo_moment_key'] ?? null;
                if ($echoMomentKey !== null && $echoMomentKey !== '' && ! in_array($echoMomentKey, $momentKeys, true)) {
                    throw new RuntimeException("Boiler {$collection} references an invalid echo_moment_key.");
                }

                if (trim((string) ($consequence['moment_narrator_hook'] ?? '')) === '') {
                    throw new RuntimeException("Boiler {$collection} requires a moment_narrator_hook.");
                }
            }
        }
    }
}
