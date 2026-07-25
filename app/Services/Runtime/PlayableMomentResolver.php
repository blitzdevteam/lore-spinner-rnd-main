<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use App\Models\Game;
use App\Models\PlayableMoment;
use App\Models\Prompt;
use App\Models\SessionAdaptation;
use App\Services\ChaosEngineService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class PlayableMomentResolver
{
    public function __construct(private ChaosEngineService $engine) {}

    public function initializeForSession(Game $game, SessionAdaptation $session): void
    {
        $cut = $session->playableCut()
            ->where('status', 'finalized')
            ->where('cut_key', 'standard')
            ->first();
        $firstMoment = $cut?->moments()->orderBy('moment_order')->first();

        if ($cut === null || $firstMoment === null) {
            throw new RuntimeException('Boiler session cannot initialize without a finalized standard cut and first moment.');
        }

        $game->update([
            'current_session_number' => $session->session_number,
            'current_session_complete' => false,
            'current_playable_session_cut_id' => $cut->id,
            'current_playable_moment_id' => $firstMoment->id,
            'playable_moment_turn_count' => 0,
            'moment_footnote' => null,
        ]);
    }

    /**
     * Soft-sanitize mapped visible suggestions for the active moment.
     * Invalid canonical mappings are demoted to freeform (null identity) so the
     * turn can still commit — mapping is optional presentation, not a hard gate.
     *
     * @param  array<int, string|array<string, mixed>>  $choices
     * @param  Collection<int, SessionAdaptation>  $sessionAdaptations
     * @return array<int, string|array{text:string,canonical_choice_id:?string,canonical_path_label:?string}>
     */
    public function sanitizeSuggestionsForCurrentMoment(
        Game $game,
        array $choices,
        Collection $sessionAdaptations,
    ): array {
        if ($game->current_playable_moment_id === null) {
            return $choices;
        }

        $moment = PlayableMoment::query()->findOrFail($game->current_playable_moment_id);

        return $this->sanitizeMappedSuggestions($choices, $moment, $sessionAdaptations, $game->id);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<int, string|array<string, mixed>>  $normalizedChoices
     * @param  Collection<int, SessionAdaptation>  $sessionAdaptations
     * @param  array{defining_choice_id:string,defining_path_label:string,defining_choice_line:?string}|null  $explicitBranch
     */
    public function applyPlayerTurn(
        Game $game,
        Prompt $sourcePrompt,
        string $storedPromptValue,
        array $result,
        array $normalizedChoices,
        Collection $sessionAdaptations,
        ?array $explicitBranch = null,
    ): Prompt {
        return DB::transaction(function () use (
            $game,
            $sourcePrompt,
            $storedPromptValue,
            $result,
            $normalizedChoices,
            $sessionAdaptations,
            $explicitBranch,
        ): Prompt {
            $lockedGame = Game::query()->whereKey($game->id)->lockForUpdate()->firstOrFail();
            $lockedSource = Prompt::query()
                ->whereKey($sourcePrompt->id)
                ->where('game_id', $lockedGame->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingResponse = Prompt::query()
                ->where('game_id', $lockedGame->id)
                ->where('source_prompt_id', $lockedSource->id)
                ->first();
            if ($existingResponse !== null) {
                return $existingResponse;
            }

            if ($lockedSource->prompt !== null) {
                throw new RuntimeException('The source prompt has already been answered.');
            }

            $currentMoment = PlayableMoment::query()
                ->whereKey($lockedGame->current_playable_moment_id)
                ->where('playable_session_cut_id', $lockedGame->current_playable_session_cut_id)
                ->lockForUpdate()
                ->firstOrFail();
            $progress = $result['moment_progress'] ?? null;

            if (
                ! is_array($progress)
                || ($progress['moment_key'] ?? null) !== $currentMoment->moment_key
                || ! is_bool($progress['exit_condition_met'] ?? null)
            ) {
                throw new RuntimeException('Narrator moment_progress is missing, stale, or invalid.');
            }

            $nextMoment = null;
            if ($progress['exit_condition_met']) {
                $nextMoment = PlayableMoment::query()
                    ->where('playable_session_cut_id', $currentMoment->playable_session_cut_id)
                    ->where('moment_order', $currentMoment->moment_order + 1)
                    ->first();
            }
            $resultingMoment = $nextMoment ?? $currentMoment;

            $normalizedChoices = $this->sanitizeMappedSuggestions(
                $normalizedChoices,
                $resultingMoment,
                $sessionAdaptations,
                $lockedGame->id,
            );

            if ($explicitBranch !== null) {
                if (
                    ($result['defining_choice_id'] ?? '') !== ''
                    && (
                        strcasecmp((string) $result['defining_choice_id'], $explicitBranch['defining_choice_id']) !== 0
                        || strtoupper((string) ($result['defining_path_label'] ?? '')) !== $explicitBranch['defining_path_label']
                    )
                ) {
                    Log::channel('narration')->warning('boiler.explicit_branch_model_mismatch', [
                        'game_id' => $lockedGame->id,
                        'source_prompt_id' => $lockedSource->id,
                        'validated_choice_id' => $explicitBranch['defining_choice_id'],
                        'validated_path_label' => $explicitBranch['defining_path_label'],
                        'model_choice_id' => $result['defining_choice_id'] ?? '',
                        'model_path_label' => $result['defining_path_label'] ?? '',
                    ]);
                }

                $result['defining_choice_id'] = $explicitBranch['defining_choice_id'];
                $result['defining_path_label'] = $explicitBranch['defining_path_label'];
                $result['defining_choice_line'] = $explicitBranch['defining_choice_line'] ?? '';
            }

            $worldState = $this->engine->mergeStateDelta(
                $lockedGame->world_state ?? $this->engine->emptyWorldState(),
                (array) $result['state_delta'],
            );
            $alignment = $this->engine->mergeAlignmentDelta(
                $lockedGame->alignment_scaffold ?? $this->engine->emptyAlignmentScaffold(),
                (array) $result['alignment_tally_delta'],
            );
            $symbolicMemory = $this->engine->appendMemory(
                $lockedGame->symbolic_memory,
                (string) $result['symbolic_memory_update'],
            );
            $branchCommit = $this->engine->resolveBranchCommit($result, $lockedGame, $sessionAdaptations);

            $footnote = $this->boundedFootnote((string) ($progress['moment_footnote'] ?? ''));
            $isFinalExit = $progress['exit_condition_met'] && $nextMoment === null;

            $lockedSource->update(['prompt' => $storedPromptValue]);
            $lockedGame->update([
                'world_state' => $worldState,
                'alignment_scaffold' => $alignment,
                'symbolic_memory' => $symbolicMemory,
                'is_climactic_choice' => (bool) $result['is_climactic_choice'],
                'defining_choice_id' => $branchCommit ? $branchCommit['defining_choice_id'] : $lockedGame->defining_choice_id,
                'defining_path_label' => $branchCommit ? $branchCommit['defining_path_label'] : $lockedGame->defining_path_label,
                'defining_choice_line' => $branchCommit ? $branchCommit['defining_choice_line'] : $lockedGame->defining_choice_line,
                'current_playable_moment_id' => $resultingMoment->id,
                'playable_moment_turn_count' => $nextMoment !== null
                    ? 0
                    : $lockedGame->playable_moment_turn_count + 1,
                'moment_footnote' => [
                    'moment_key' => $resultingMoment->moment_key,
                    'text' => $footnote,
                    'source_prompt_id' => $lockedSource->id,
                ],
                'current_session_complete' => $isFinalExit,
            ]);

            $responsePrompt = $lockedGame->prompts()->create([
                'source_prompt_id' => $lockedSource->id,
                'session_number' => $lockedGame->current_session_number,
                'response' => $result['response'],
                'choices' => $normalizedChoices,
            ]);

            Log::channel('narration')->info('boiler.moment_turn_committed', [
                'game_id' => $lockedGame->id,
                'session_number' => $lockedGame->current_session_number,
                'source_prompt_id' => $lockedSource->id,
                'moment_key' => $currentMoment->moment_key,
                'anchor_event_id' => $currentMoment->anchor_event_id,
                'exit_condition_met' => $progress['exit_condition_met'],
                'result' => $isFinalExit ? 'completed' : ($nextMoment !== null ? 'advanced' : 'retained'),
                'next_moment_key' => $nextMoment?->moment_key,
                'moment_footnote' => $footnote,
            ]);

            return $responsePrompt;
        });
    }

    /**
     * @param  array<int, string|array<string, mixed>>  $choices
     * @param  Collection<int, SessionAdaptation>  $sessionAdaptations
     * @return array<int, string|array{text:string,canonical_choice_id:?string,canonical_path_label:?string}>
     */
    private function sanitizeMappedSuggestions(
        array $choices,
        PlayableMoment $resultingMoment,
        Collection $sessionAdaptations,
        string $gameId,
    ): array {
        $allowedChoiceIds = (array) data_get($resultingMoment->scene_card, 'choice_ids', []);

        return array_map(function ($choice) use ($allowedChoiceIds, $sessionAdaptations, $gameId, $resultingMoment) {
            if (! is_array($choice) || empty($choice['canonical_choice_id'])) {
                return $choice;
            }

            $choiceId = (string) $choice['canonical_choice_id'];
            $pathLabel = strtoupper((string) ($choice['canonical_path_label'] ?? ''));

            if (! in_array($choiceId, $allowedChoiceIds, true)) {
                return $this->demoteMappedSuggestion(
                    $choice,
                    $gameId,
                    $choiceId,
                    $pathLabel,
                    'not_bound_to_moment',
                    $resultingMoment->moment_key,
                );
            }

            $choiceSession = $this->engine->extractSessionNumber($choiceId);
            $session = $sessionAdaptations->firstWhere('session_number', $choiceSession);
            $authoredChoice = $this->findAuthoredChoice($session, $choiceId);
            $labels = collect((array) ($authoredChoice['options'] ?? []))
                ->pluck('label')
                ->map(fn ($label): string => strtoupper((string) $label))
                ->all();

            if ($authoredChoice === null) {
                return $this->demoteMappedSuggestion(
                    $choice,
                    $gameId,
                    $choiceId,
                    $pathLabel,
                    'choice_not_in_design',
                    $resultingMoment->moment_key,
                );
            }

            if (! in_array($pathLabel, $labels, true)) {
                return $this->demoteMappedSuggestion(
                    $choice,
                    $gameId,
                    $choiceId,
                    $pathLabel,
                    'path_not_in_authored_options',
                    $resultingMoment->moment_key,
                );
            }

            return $choice;
        }, $choices);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAuthoredChoice(?SessionAdaptation $session, string $choiceId): ?array
    {
        if ($session === null) {
            return null;
        }

        $design = (array) ($session->session_choice_design ?? []);
        foreach (['branching_choices', 'emotional_choices'] as $bucket) {
            $match = collect((array) ($design[$bucket] ?? []))
                ->firstWhere('choice_id', $choiceId);
            if (is_array($match)) {
                return $match;
            }
        }

        return null;
    }

    /**
     * @param  array{text:string,canonical_choice_id:?string,canonical_path_label:?string}  $choice
     * @return array{text:string,canonical_choice_id:null,canonical_path_label:null}
     */
    private function demoteMappedSuggestion(
        array $choice,
        string $gameId,
        string $choiceId,
        string $pathLabel,
        string $reason,
        string $momentKey,
    ): array {
        Log::channel('narration')->warning('boiler.mapped_suggestion_demoted', [
            'game_id' => $gameId,
            'moment_key' => $momentKey,
            'canonical_choice_id' => $choiceId,
            'canonical_path_label' => $pathLabel,
            'reason' => $reason,
            'suggestion_text' => mb_substr((string) ($choice['text'] ?? ''), 0, 120),
        ]);

        return [
            'text' => (string) ($choice['text'] ?? ''),
            'canonical_choice_id' => null,
            'canonical_path_label' => null,
        ];
    }

    private function boundedFootnote(string $footnote): string
    {
        $words = preg_split('/\s+/u', trim($footnote), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) {
            throw new RuntimeException('Boiler player turns require a non-empty moment footnote.');
        }

        if (count($words) > 30) {
            Log::channel('narration')->warning('boiler.moment_footnote_truncated', [
                'original_word_count' => count($words),
            ]);
            $words = array_slice($words, 0, 30);
        }

        return implode(' ', $words);
    }
}
