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
     * @param  array<int, string|array<string, mixed>>  $choices
     * @param  Collection<int, SessionAdaptation>  $sessionAdaptations
     */
    public function validateSuggestionsForCurrentMoment(
        Game $game,
        array $choices,
        Collection $sessionAdaptations,
    ): void {
        if ($game->current_playable_moment_id === null) {
            return;
        }

        $moment = PlayableMoment::query()->findOrFail($game->current_playable_moment_id);
        $this->validateSuggestions($choices, $moment, $sessionAdaptations);
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

            $this->validateSuggestions($normalizedChoices, $resultingMoment, $sessionAdaptations);

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
     */
    private function validateSuggestions(
        array $choices,
        PlayableMoment $resultingMoment,
        Collection $sessionAdaptations,
    ): void {
        $allowedChoiceIds = (array) data_get($resultingMoment->scene_card, 'choice_ids', []);

        foreach ($choices as $choice) {
            if (! is_array($choice) || empty($choice['canonical_choice_id'])) {
                continue;
            }

            $choiceId = (string) $choice['canonical_choice_id'];
            $pathLabel = strtoupper((string) ($choice['canonical_path_label'] ?? ''));
            if (! in_array($choiceId, $allowedChoiceIds, true)) {
                throw new RuntimeException('A mapped visible suggestion is not bound to the resulting active moment.');
            }

            $choiceSession = $this->engine->extractSessionNumber($choiceId);
            $session = $sessionAdaptations->firstWhere('session_number', $choiceSession);
            $authoredChoice = collect((array) data_get($session, 'session_choice_design.branching_choices', []))
                ->firstWhere('choice_id', $choiceId);
            $labels = collect((array) ($authoredChoice['options'] ?? []))
                ->pluck('label')
                ->map(fn ($label): string => strtoupper((string) $label))
                ->all();

            if (! in_array($pathLabel, $labels, true)) {
                throw new RuntimeException('A mapped visible suggestion references a nonexistent authored path.');
            }
        }
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
