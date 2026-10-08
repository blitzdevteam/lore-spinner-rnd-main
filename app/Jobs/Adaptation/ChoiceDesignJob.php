<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\BannedProseValidator;
use App\Ai\Adaptation\ChoiceDesignCanonicalizer;
use App\Ai\Adaptation\PlayableProtagonist;
use App\Ai\Adaptation\StorySessionMapSlice;
use App\Ai\Adaptation\VoiceProfilePromptSlice;
use App\Ai\Agents\Adaptation\ChoiceDesignAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Exceptions\Adaptation\ArtifactValidationException;
use App\Models\Story;
use App\Support\Adaptation\GeneratesValidatedArtifact;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ChoiceDesignJob implements ShouldQueue
{
    use Batchable, GeneratesValidatedArtifact, Queueable;

    public int $tries = 2;

    public int $timeout = 1_100;

    public int $backoff = 60;

    public function __construct(
        private Story $story,
        private int $sessionNumber,
    ) {
        $this->onQueue('adaptation');
    }

    /** @throws Throwable */
    public function handle(ChoiceDesignCanonicalizer $canonicalizer): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $adaptation = $this->story->adaptation;
        $session = $adaptation->sessionAdaptations()->where('session_number', $this->sessionNumber)->firstOrFail();

        if (empty($adaptation->voice_profile)) {
            throw new RuntimeException(
                'voice_profile missing — Voice Lock must complete before Phase 5 (ChoiceDesign)'
            );
        }

        // V2.3: Phase 5 requires anchor_card (binary/local rules ground all authored outcomes).
        // dnaBansAndAnchorCard() throws RuntimeException if anchor_card is absent.
        // Voice Anchor exemplar prose is intentionally excluded — token budget.
        $voiceProfile = VoiceProfilePromptSlice::dnaBansAndAnchorCard((array) ($adaptation->voice_profile ?? []));

        // D4 Patch: first_choice_spec is the Phase 3 output consumed by Task 1.
        // Throw explicitly if Phase 3 has not produced a spec — no silent pass.
        $firstChoiceSpec = $session->entry_point_diagnosis['first_choice_spec'] ?? null;

        if ($firstChoiceSpec === null) {
            throw new RuntimeException(sprintf(
                'first_choice_spec missing from entry_point_diagnosis (story %d, session %d). '
                .'Phase 3 (EntryPointDiagnosisJob) must complete before Phase 5 (ChoiceDesign).',
                $this->story->id,
                $this->sessionNumber,
            ));
        }

        try {
            $session->update(['session_status' => SessionAdaptationStatusEnum::CHOICE_DESIGN]);

            $ipAudit = $adaptation->ip_audit;

            // Use ip_trimming chapter segments for session-accurate source pages.
            $choiceMomentPages = $this->story->getSessionTrimmedText($this->sessionNumber);

            $prompt = view('ai.agents.adaptation.choice-design.prompt', [
                'beatMap' => $session->session_architecture,
                'storySessionMap' => StorySessionMapSlice::forSession((array) $adaptation->story_session_map, $this->sessionNumber),
                'protagonistCoreTrait' => $ipAudit['bounded_agency']['evidence'] ?? '',
                'protagonist' => PlayableProtagonist::for($this->story),
                'emotionalPromise' => $session->entry_point_diagnosis['emotional_promise'] ?? '',
                'sessionNumber' => $this->sessionNumber,
                'choiceMomentPages' => $choiceMomentPages,
                'voiceProfile' => $voiceProfile,
                'firstChoiceSpec' => $firstChoiceSpec,
                'boilerDraft' => $adaptation->boiler_enabled ? $session->boiler_draft : null,
            ])->render();

            $choiceDesign = $adaptation->boiler_enabled
                ? $this->generateValidatedChoiceDesign(
                    $prompt,
                    $canonicalizer,
                    (array) $session->boiler_draft,
                )
                : (new ChoiceDesignAgent(false))->prompt($prompt)->toArray();

            $session->update(['session_choice_design' => $choiceDesign]);

            $this->enrichBranchDimensionRegistry($adaptation, $choiceDesign);
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
     * V2 shape: `branching_choices` is an array of four choice objects. Each carries
     * an `options[]` collection (label A/B/C) instead of the legacy
     * option_a/option_b/option_c keys.
     *
     * @param  array<string, mixed>  $choiceDesign
     */
    private function enrichBranchDimensionRegistry($adaptation, array $choiceDesign): void
    {
        $sessionMap = $adaptation->story_session_map;
        $dimensions = $sessionMap['branch_dimensions'] ?? [];

        $branchingChoices = (array) ($choiceDesign['branching_choices'] ?? []);

        foreach ($branchingChoices as $choice) {
            if (! is_array($choice) || empty($choice['what_this_choice_tracks'])) {
                continue;
            }

            $tracked = Str::snake(Str::lower($choice['what_this_choice_tracks']));
            $existingIndex = null;

            foreach ($dimensions as $i => $dim) {
                if (Str::snake(Str::lower($dim['dimension_name'] ?? '')) === $tracked) {
                    $existingIndex = $i;
                    break;
                }
            }

            $pathData = [];
            foreach ((array) ($choice['options'] ?? []) as $option) {
                $label = mb_strtolower((string) ($option['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $pathData['option_'.$label] = (string) ($option['text'] ?? $option['downstream_effect'] ?? '');
            }

            if ($existingIndex !== null) {
                $dimensions[$existingIndex]['possible_paths'] = $pathData;
                $dimensions[$existingIndex]['session_introduced'] ??= $this->sessionNumber;
                $dimensions[$existingIndex]['choice_id'] = $choice['choice_id'] ?? null;
            } else {
                $dimensions[] = [
                    'dimension_name' => $tracked,
                    'description' => $choice['what_this_choice_tracks'],
                    'possible_paths' => $pathData,
                    'origin' => 'phase_5',
                    'session_introduced' => $this->sessionNumber,
                    'choice_id' => $choice['choice_id'] ?? null,
                ];
            }
        }

        $sessionMap['branch_dimensions'] = $dimensions;
        $adaptation->update(['story_session_map' => $sessionMap]);
    }

    /**
     * @param  array<string, mixed>  $choiceDesign
     */
    private function validateCanonicalTopology(array $choiceDesign): void
    {
        $branchingChoices = (array) ($choiceDesign['branching_choices'] ?? []);
        $expectedCategories = ['IDENTITY', 'METHODOLOGY', 'MORAL_WEIGHT', 'SESSION_END_HOOK'];

        if (count($branchingChoices) !== 4) {
            throw new RuntimeException('Choice Design must preserve exactly four canonical branching choices.');
        }

        foreach ($branchingChoices as $index => $choice) {
            $expectedChoiceId = "S{$this->sessionNumber}_C".($index + 1);
            if (
                ! is_array($choice)
                || ($choice['choice_id'] ?? null) !== $expectedChoiceId
                || ($choice['category'] ?? null) !== $expectedCategories[$index]
            ) {
                throw new RuntimeException(
                    'Choice Design must preserve canonical IDs and IDENTITY / METHODOLOGY / MORAL_WEIGHT / SESSION_END_HOOK order.'
                );
            }

            $labels = collect((array) ($choice['options'] ?? []))
                ->pluck('label')
                ->map(fn ($label): string => mb_strtoupper((string) $label))
                ->sort()
                ->values()
                ->all();

            if ($labels !== ['A', 'B', 'C']) {
                throw new RuntimeException('Every canonical branching choice must preserve exactly A/B/C options.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $choiceDesign
     * @param  array<string, mixed>  $draft
     */
    private function validateBoilerBindings(array $choiceDesign, array $draft): void
    {
        $momentKeys = collect((array) ($draft['moment_drafts'] ?? []))
            ->pluck('moment_key')
            ->filter()
            ->values()
            ->all();

        if ($momentKeys === []) {
            throw new RuntimeException('Boiler-enabled Choice Design requires a validated Boiler Draft.');
        }

        foreach (['branching_choices', 'emotional_choices', 'posture_shifts'] as $collection) {
            foreach ((array) ($choiceDesign[$collection] ?? []) as $choice) {
                if (! is_array($choice) || ! in_array($choice['moment_key'] ?? null, $momentKeys, true)) {
                    throw new RuntimeException("Every Boiler-enabled {$collection} entry must bind to a valid moment_key.");
                }
            }
        }

        $sessionEndChoice = collect((array) ($choiceDesign['branching_choices'] ?? []))
            ->firstWhere('category', 'SESSION_END_HOOK');
        $finalMomentKey = end($momentKeys);
        if (
            ! is_array($sessionEndChoice)
            || ! is_string($finalMomentKey)
            || ($sessionEndChoice['moment_key'] ?? null) !== $finalMomentKey
        ) {
            throw new RuntimeException(
                'The SESSION_END_HOOK canonical choice must bind to the final Draft moment.'
            );
        }

    }

    /**
     * A branching option is something the player does. An option that never
     * addresses the player decides what another character does instead.
     *
     * @param  array<string, mixed>  $choiceDesign
     */
    private function validatePlayerOwnedOptions(array $choiceDesign): void
    {
        $failures = [];

        foreach ((array) ($choiceDesign['branching_choices'] ?? []) as $choice) {
            foreach ((array) ($choice['options'] ?? []) as $option) {
                $text = (string) ($option['text'] ?? '');

                if (preg_match('/\byou(?:r|rs|rself)?\b/iu', $text) !== 1) {
                    $failures[] = sprintf('%s option %s is not the player\'s own action: "%s". Rewrite it as what the protagonist does, addressed as you.', $choice['choice_id'] ?? '?', $option['label'] ?? '?', $text);
                }
            }
        }

        if ($failures !== []) {
            throw new RuntimeException(implode("\n", $failures));
        }
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function generateValidatedChoiceDesign(
        string $originalPrompt,
        ChoiceDesignCanonicalizer $canonicalizer,
        array $draft,
    ): array {
        return $this->generateValidatedArtifact(
            artifact: 'choice_design',
            originalPrompt: $originalPrompt,
            generate: fn (string $prompt): array => BannedProseValidator::neutralizeArtifact($canonicalizer->canonicalize(
                $this->completeArtifact((new ChoiceDesignAgent(true))->prompt($prompt), 'choice design'),
                $draft,
            )),
            validate: function (array $choiceDesign) use ($draft): void {
                $this->validateCanonicalTopology($choiceDesign);
                $this->validateBoilerBindings($choiceDesign, $draft);
                $this->validatePlayerOwnedOptions($choiceDesign);
            },
            repairPrompt: fn (string $error, array $previousChoiceDesign): string => view(
                'ai.agents.adaptation.choice-design.repair-prompt',
                [
                    'validationError' => $error,
                    'previousChoiceDesign' => $previousChoiceDesign,
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
