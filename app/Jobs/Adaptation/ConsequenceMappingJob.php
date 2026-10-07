<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\BannedProseValidator;
use App\Ai\Adaptation\ConsequenceMapCanonicalizer;
use App\Ai\Adaptation\ConsequenceMapValidator;
use App\Ai\Adaptation\PlayableProtagonist;
use App\Ai\Adaptation\SessionHandoff;
use App\Ai\Adaptation\VoiceProfilePromptSlice;
use App\Ai\Agents\Adaptation\ConsequenceMappingAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Exceptions\Adaptation\ArtifactValidationException;
use App\Models\Story;
use App\Support\Adaptation\GeneratesValidatedArtifact;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

final class ConsequenceMappingJob implements ShouldQueue
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

    /** @throws Throwable */
    public function handle(
        ConsequenceMapCanonicalizer $canonicalizer,
        ConsequenceMapValidator $validator,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $adaptation = $this->story->adaptation;
        $session = $adaptation->sessionAdaptations()->where('session_number', $this->sessionNumber)->firstOrFail();

        if (empty($adaptation->voice_profile)) {
            throw new RuntimeException(
                'voice_profile missing — Voice Lock must complete before Phase 6 (ConsequenceMapping)'
            );
        }

        try {
            $session->update(['session_status' => SessionAdaptationStatusEnum::CONSEQUENCE_MAPPING]);

            $choiceDesign = $session->session_choice_design;

            $prompt = view('ai.agents.adaptation.consequence-mapping.prompt', [
                // V2 shape: pass through the full branching_choices array
                // (4 entries) plus the persistent state schema + reactivity
                // rules so the consequence map can name specific NPCs / flags.
                'branchingChoices' => (array) ($choiceDesign['branching_choices'] ?? []),
                'emotionalChoices' => (array) ($choiceDesign['emotional_choices'] ?? []),
                'storySessionMap' => $adaptation->story_session_map,
                'persistentStateSchema' => $adaptation->story_session_map['persistent_state_schema'] ?? [],
                'worldReactivityRules' => $adaptation->story_session_map['world_reactivity_rules'] ?? [],
                'protagonistCoreTrait' => $adaptation->ip_audit['bounded_agency']['evidence'] ?? '',
                'protagonist' => PlayableProtagonist::for($this->story),
                'nextSessionOpening' => SessionHandoff::nextSessionOpening($session),
                'isFinalSession' => SessionHandoff::isFinalSession($session),
                'sessionNumber' => $this->sessionNumber,
                'voiceProfile' => VoiceProfilePromptSlice::dnaAndBans((array) ($adaptation->voice_profile ?? [])),
                'boilerDraft' => $adaptation->boiler_enabled ? $session->boiler_draft : null,
            ])->render();

            $consequenceMap = $adaptation->boiler_enabled
                ? $this->generateValidatedMap(
                    $prompt,
                    $canonicalizer,
                    $validator,
                    (array) $choiceDesign,
                    (array) $session->boiler_draft,
                )
                : (new ConsequenceMappingAgent(false))->prompt($prompt)->toArray();

            $session->update(['choice_consequence_map' => $consequenceMap]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);

            if ($throwable instanceof ArtifactValidationException) {
                $this->failTerminally($throwable);

                return;
            }

            throw $throwable;
        }
    }

    /**
     * @param  array<string, mixed>  $choiceDesign
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function generateValidatedMap(
        string $originalPrompt,
        ConsequenceMapCanonicalizer $canonicalizer,
        ConsequenceMapValidator $validator,
        array $choiceDesign,
        array $draft,
    ): array {
        return $this->generateValidatedArtifact(
            artifact: 'consequence_mapping',
            originalPrompt: $originalPrompt,
            generate: fn (string $prompt): array => BannedProseValidator::neutralizeArtifact($canonicalizer->canonicalize(
                $this->completeArtifact((new ConsequenceMappingAgent(true))->prompt($prompt), 'consequence mapping'),
                $choiceDesign,
            )),
            validate: fn (array $consequenceMap) => $validator->validate($consequenceMap, $choiceDesign, $draft),
            repairPrompt: fn (string $error, array $previousConsequenceMap): string => view(
                'ai.agents.adaptation.consequence-mapping.repair-prompt',
                [
                    'validationError' => $error,
                    'previousConsequenceMap' => $previousConsequenceMap,
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
