<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\ConsequenceMapCanonicalizer;
use App\Ai\Adaptation\ConsequenceMapValidator;
use App\Ai\Adaptation\VoiceProfilePromptSlice;
use App\Ai\Agents\Adaptation\ConsequenceMappingAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Story;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class ConsequenceMappingJob implements ShouldQueue
{
    use Batchable, Queueable;

    private const int MAX_VALIDATION_ATTEMPTS = 2;

    public int $tries = 1;

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
        $prompt = $originalPrompt;
        $lastValidationError = null;

        for ($attempt = 1; $attempt <= self::MAX_VALIDATION_ATTEMPTS; $attempt++) {
            $consequenceMap = (new ConsequenceMappingAgent(true))->prompt($prompt)->toArray();
            $consequenceMap = $canonicalizer->canonicalize($consequenceMap, $choiceDesign);

            try {
                $validator->validate($consequenceMap, $choiceDesign, $draft);

                if ($attempt > 1) {
                    Log::info('boiler.consequence_mapping_corrected', [
                        'story_id' => $this->story->id,
                        'session_number' => $this->sessionNumber,
                        'generation_attempt' => $attempt,
                    ]);
                }

                return $consequenceMap;
            } catch (RuntimeException $validationError) {
                $lastValidationError = $validationError;
                Log::warning('boiler.consequence_mapping_validation_failed', [
                    'story_id' => $this->story->id,
                    'session_number' => $this->sessionNumber,
                    'generation_attempt' => $attempt,
                    'will_correct' => $attempt < self::MAX_VALIDATION_ATTEMPTS,
                    'error' => $validationError->getMessage(),
                ]);

                if ($attempt < self::MAX_VALIDATION_ATTEMPTS) {
                    $prompt = view('ai.agents.adaptation.consequence-mapping.repair-prompt', [
                        'validationError' => $validationError->getMessage(),
                        'previousConsequenceMap' => $consequenceMap,
                        'originalPrompt' => $originalPrompt,
                    ])->render();
                }
            }
        }

        throw $lastValidationError
            ?? new RuntimeException('Consequence Mapping validation failed without an error.');
    }
}
