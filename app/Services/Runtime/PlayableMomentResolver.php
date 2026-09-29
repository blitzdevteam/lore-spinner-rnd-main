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
    /**
     * How many consecutive turns the narrator may declare the session finished on
     * the final room before the authored ending gate stops holding the player.
     */
    private const ENDING_BLOCK_TOLERANCE = 3;

    /**
     * Furthest the pointer may jump in one turn when the narrator reports the
     * story has overtaken the active card.
     */
    private const MAX_CATCH_UP_CARDS = 3;

    /**
     * Exit attempts on a decision card held back before the pointer is released
     * anyway, so a narrator that never offers the choice cannot trap the player.
     */
    private const CHOICE_BLOCK_TOLERANCE = 3;

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
                // Soft structural fallback — rare malformed metadata only.
                // Never invent earned exits; never kill the player turn; never second callAgent.
                // Progression is prompt-contract led, not this repair path.
                $hadProgressObject = is_array($progress);
                $rawMomentKey = $hadProgressObject ? ($progress['moment_key'] ?? null) : null;
                $footnote = $hadProgressObject ? ($progress['moment_footnote'] ?? null) : null;
                $rawRoute = $hadProgressObject ? (string) ($progress['route_relation'] ?? '') : '';
                $rawImpact = $hadProgressObject ? (string) ($progress['impact_scope'] ?? '') : '';
                $catchUpCandidate = $hadProgressObject ? mb_trim((string) ($progress['caught_up_to_moment_key'] ?? '')) : '';
                if ($catchUpCandidate === '' && is_string($rawMomentKey) && $rawMomentKey !== $currentMoment->moment_key) {
                    $catchUpCandidate = mb_trim($rawMomentKey);
                }
                $progress = [
                    'moment_key' => $currentMoment->moment_key,
                    'exit_condition_met' => false,
                    'caught_up_to_moment_key' => $catchUpCandidate,
                    'route_relation' => $rawRoute,
                    'impact_scope' => $rawImpact,
                    'moment_footnote' => $footnote,
                ];
                $result['moment_progress'] = $progress;

                Log::channel('narration')->warning('boiler.moment_progress_repaired_on_commit', [
                    'game_id' => $lockedGame->id,
                    'source_prompt_id' => $lockedSource->id,
                    'active_moment_key' => $currentMoment->moment_key,
                    'had_progress_object' => $hadProgressObject,
                    'raw_moment_key' => $rawMomentKey,
                ]);
            }

            $carried = $this->carriedMomentState($lockedGame, $currentMoment);
            $catchUp = $this->catchUpTarget(
                $currentMoment,
                (string) ($progress['caught_up_to_moment_key'] ?? ''),
                $lockedGame->id,
                $carried['catch_up_candidate'],
            );
            $nextMoment = $catchUp['target'];
            $catchUpCandidate = $catchUp['candidate'];
            $confirmedCatchUp = $catchUp['confirmed'];
            $caughtUp = $nextMoment !== null;

            if ($caughtUp) {
                $progress['exit_condition_met'] = true;
                $result['moment_progress'] = $progress;
            } elseif ($progress['exit_condition_met']) {
                $nextMoment = PlayableMoment::query()
                    ->where('playable_session_cut_id', $currentMoment->playable_session_cut_id)
                    ->where('moment_order', $currentMoment->moment_order + 1)
                    ->first();
            } elseif ($this->heldPastGravity($currentMoment, (int) $lockedGame->playable_moment_turn_count)) {
                $nextMoment = PlayableMoment::query()
                    ->where('playable_session_cut_id', $currentMoment->playable_session_cut_id)
                    ->where('moment_order', $currentMoment->moment_order + 1)
                    ->first();

                if ($nextMoment !== null) {
                    $progress['exit_condition_met'] = true;
                    $result['moment_progress'] = $progress;
                    Log::channel('narration')->warning('boiler.moment_advanced_past_gravity', [
                        'game_id' => $lockedGame->id,
                        'from_moment_key' => $currentMoment->moment_key,
                        'to_moment_key' => $nextMoment->moment_key,
                        'turn_count' => $lockedGame->playable_moment_turn_count + 1,
                    ]);
                }
            }

            // Sanitize mapped suggestions against the pre-exit (current) moment so
            // authored choice maps for the room being resolved are not demoted when
            // the pointer advances in the same response.
            $normalizedChoices = $this->sanitizeMappedSuggestions(
                $normalizedChoices,
                $currentMoment,
                $sessionAdaptations,
                $lockedGame->id,
            );

            if ($explicitBranch !== null) {
                if (
                    ($result['defining_choice_id'] ?? '') !== ''
                    && (
                        strcasecmp((string) $result['defining_choice_id'], $explicitBranch['defining_choice_id']) !== 0
                        || mb_strtoupper((string) ($result['defining_path_label'] ?? '')) !== $explicitBranch['defining_path_label']
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

            $branchCommit = $this->engine->resolveBranchCommit($result, $lockedGame, $sessionAdaptations);
            $committedChoiceId = $explicitBranch['defining_choice_id']
                ?? ($branchCommit['defining_choice_id'] ?? $lockedGame->defining_choice_id);
            $choiceBlockedTurns = $carried['choice_blocked_turns'];

            if ($nextMoment !== null && ! $confirmedCatchUp) {
                $pendingChoiceIds = $this->unresolvedBranchingChoiceIds(
                    $currentMoment,
                    $sessionAdaptations,
                    (int) $lockedGame->current_session_number,
                    $committedChoiceId,
                );

                if ($pendingChoiceIds !== []) {
                    $choiceBlockedTurns++;
                    $logContext = [
                        'game_id' => $lockedGame->id,
                        'moment_key' => $currentMoment->moment_key,
                        'pending_choice_ids' => $pendingChoiceIds,
                        'committed_choice_id' => $committedChoiceId,
                        'blocked_turns' => $choiceBlockedTurns,
                    ];

                    if ($choiceBlockedTurns >= self::CHOICE_BLOCK_TOLERANCE) {
                        Log::channel('narration')->warning('boiler.moment_exit_released_without_choice', $logContext);
                    } else {
                        Log::channel('narration')->info('boiler.moment_exit_blocked_awaiting_choice', $logContext);
                        $nextMoment = null;
                        $caughtUp = false;
                        $progress['exit_condition_met'] = false;
                        $result['moment_progress'] = $progress;
                    }
                }
            }
            $resultingMoment = $nextMoment ?? $currentMoment;

            $worldState = $this->engine->mergeStateDelta(
                $lockedGame->world_state ?? $this->engine->emptyWorldState(),
                (array) $result['state_delta'],
            );

            // Phase A: track which MUST REINTRODUCE facts have landed.
            // The narrator reports them in reintroduced_facts; we merge
            // them into world_state.reintroduced_facts so they stop
            // being injected on future turns.
            $reportedReintro = (array) ($result['reintroduced_facts'] ?? []);
            if ($reportedReintro !== []) {
                $tracker = new \App\Ai\Adaptation\ReintroTracker;
                $newKeys = $tracker->keysFromReportedFacts($reportedReintro);
                $existing = (array) ($worldState['reintroduced_facts'] ?? []);
                $worldState['reintroduced_facts'] = array_values(array_unique(array_merge($existing, $newKeys)));
            }
            $alignment = $this->engine->mergeAlignmentDelta(
                $lockedGame->alignment_scaffold ?? $this->engine->emptyAlignmentScaffold(),
                (array) $result['alignment_tally_delta'],
            );
            $symbolicMemory = $this->engine->appendMemory(
                $lockedGame->symbolic_memory,
                (string) $result['symbolic_memory_update'],
            );
            $footnote = $this->boundedFootnote(
                (string) ($progress['moment_footnote'] ?? ''),
                $lockedGame->id,
            );

            $routeRelation = mb_strtolower(mb_trim((string) ($progress['route_relation'] ?? '')));
            $impactScope = mb_strtolower(mb_trim((string) ($progress['impact_scope'] ?? '')));

            $detourBeatsUsed = $this->nextDetourBeats(
                $carried['detour_beats_used'],
                $routeRelation,
                $nextMoment !== null,
            );

            $isFinalExit = $progress['exit_condition_met'] && $nextMoment === null;
            $endingBlockedTurns = $carried['ending_blocked_turns'];

            if ($isFinalExit && ! $this->authoredSessionEndingChoiceResolved($currentMoment, $committedChoiceId)) {
                $endingBlockedTurns++;

                if ($endingBlockedTurns >= self::ENDING_BLOCK_TOLERANCE) {
                    // The narrator has called this session finished repeatedly without
                    // the authored ending choice landing. Holding the player in a room
                    // that has run out of story is a worse break than accepting a close
                    // the fiction has already reached.
                    Log::channel('narration')->warning('boiler.session_complete_released_after_repeated_block', [
                        'game_id' => $lockedGame->id,
                        'moment_key' => $currentMoment->moment_key,
                        'committed_choice_id' => $committedChoiceId,
                        'blocked_turns' => $endingBlockedTurns,
                        'required_choice_ids' => (array) data_get($currentMoment->scene_card, 'choice_ids', []),
                    ]);
                } else {
                    $isFinalExit = false;
                    Log::channel('narration')->info('boiler.session_complete_blocked_awaiting_ending_choice', [
                        'game_id' => $lockedGame->id,
                        'moment_key' => $currentMoment->moment_key,
                        'committed_choice_id' => $committedChoiceId,
                        'blocked_turns' => $endingBlockedTurns,
                        'required_choice_ids' => (array) data_get($currentMoment->scene_card, 'choice_ids', []),
                    ]);
                }
            }

            $momentResult = $isFinalExit
                ? 'completed'
                : ($nextMoment !== null ? 'advanced' : 'retained');

            $lockedSource->update(['prompt' => $storedPromptValue]);
            $lockedGame->update([
                'world_state' => $worldState,
                'alignment_scaffold' => $alignment,
                'symbolic_memory' => $symbolicMemory,
                // A turn that genuinely changed the world earns the full state load next
                // turn, so the narrator can see what it must now honour.
                'is_climactic_choice' => (bool) $result['is_climactic_choice'] || $impactScope === 'persistent',
                'defining_choice_id' => $branchCommit ? $branchCommit['defining_choice_id'] : $lockedGame->defining_choice_id,
                'defining_path_label' => $branchCommit ? $branchCommit['defining_path_label'] : $lockedGame->defining_path_label,
                'defining_choice_line' => $branchCommit ? $branchCommit['defining_choice_line'] : $lockedGame->defining_choice_line,
                'current_playable_moment_id' => $resultingMoment->id,
                'playable_moment_turn_count' => $nextMoment !== null
                    ? 0
                    : $lockedGame->playable_moment_turn_count + 1,
                'moment_footnote' => [
                    'moment_key' => $resultingMoment->moment_key,
                    'text' => $footnote !== '' ? $footnote : null,
                    'source_prompt_id' => $lockedSource->id,
                    'detour_beats_used' => $detourBeatsUsed,
                    'ending_blocked_turns' => $nextMoment !== null ? 0 : $endingBlockedTurns,
                    'choice_blocked_turns' => $nextMoment !== null ? 0 : $choiceBlockedTurns,
                    'catch_up_candidate' => $nextMoment !== null ? null : $catchUpCandidate,
                ],
                'current_session_complete' => $isFinalExit,
            ]);

            $responsePrompt = $lockedGame->prompts()->create([
                'source_prompt_id' => $lockedSource->id,
                'session_number' => $lockedGame->current_session_number,
                'moment_key' => $currentMoment->moment_key,
                'exit_condition_met' => (bool) $progress['exit_condition_met'],
                'moment_result' => $momentResult,
                'moment_footnote' => $footnote !== '' ? $footnote : null,
                'route_relation' => $routeRelation !== '' ? $routeRelation : null,
                'impact_scope' => $impactScope !== '' ? $impactScope : null,
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
                'result' => $momentResult,
                'next_moment_key' => $nextMoment?->moment_key,
                'caught_up' => $caughtUp,
                'moment_footnote' => $footnote !== '' ? $footnote : null,
                'route_relation' => $routeRelation,
                'impact_scope' => $impactScope,
                'detour_beats_used' => $detourBeatsUsed,
            ]);

            return $responsePrompt;
        });
    }

    /**
     * A card that has outrun its gravity budget is advanced even when the
     * narrator keeps holding it; the prose has almost always moved on by then.
     * An untaken authored branching choice still holds the card through the
     * decision-point gate, so the branch gets its turns before any release.
     */
    private function heldPastGravity(PlayableMoment $currentMoment, int $completedTurns): bool
    {
        $gravityAfter = (int) data_get($currentMoment->scene_card, 'turn_budget.gravity_after', 0);

        if ($gravityAfter <= 0) {
            return false;
        }

        return $completedTurns + 1 > $gravityAfter;
    }

    /**
     * Move the pointer to the card the prose has already reached.
     *
     * The narrator reports it when the story has overtaken the active card,
     * which otherwise leaves the pointer several cards behind the fiction and
     * keeps injecting a scene the player has already left. A jump further than
     * the bound, or over a card carrying an authored choice, is only taken when
     * the narrator reports the same card on two consecutive turns: by then the
     * prose has left those cards behind and holding the pointer only desyncs it.
     *
     * @return array{target:?PlayableMoment, candidate:?string, confirmed:bool}
     */
    private function catchUpTarget(PlayableMoment $currentMoment, string $targetKey, string $gameId, ?string $previousCandidate): array
    {
        $none = ['target' => null, 'candidate' => null, 'confirmed' => false];
        $targetKey = mb_trim($targetKey);
        if ($targetKey === '' || $targetKey === $currentMoment->moment_key) {
            return $none;
        }

        $target = PlayableMoment::query()
            ->where('playable_session_cut_id', $currentMoment->playable_session_cut_id)
            ->where('moment_key', $targetKey)
            ->first();

        $rejection = match (true) {
            $target === null => 'unknown_key',
            $target->moment_order <= $currentMoment->moment_order => 'not_later',
            $target->moment_order - $currentMoment->moment_order > self::MAX_CATCH_UP_CARDS => 'too_far',
            default => null,
        };

        if ($rejection === null) {
            $skipped = PlayableMoment::query()
                ->where('playable_session_cut_id', $currentMoment->playable_session_cut_id)
                ->where('moment_order', '>', $currentMoment->moment_order)
                ->where('moment_order', '<', $target->moment_order)
                ->get();

            if ($skipped->contains(fn (PlayableMoment $moment): bool => (array) data_get($moment->scene_card, 'choice_ids', []) !== [])) {
                $rejection = 'would_skip_authored_choice';
            }
        }

        $confirmable = in_array($rejection, ['too_far', 'would_skip_authored_choice'], true);

        if ($confirmable && $previousCandidate === $targetKey) {
            Log::channel('narration')->info('boiler.moment_caught_up_confirmed', [
                'game_id' => $gameId,
                'from_moment_key' => $currentMoment->moment_key,
                'target_moment_key' => $targetKey,
                'overridden_reason' => $rejection,
            ]);

            return ['target' => $target, 'candidate' => null, 'confirmed' => true];
        }

        Log::channel('narration')->info($rejection === null ? 'boiler.moment_caught_up' : 'boiler.moment_catch_up_rejected', [
            'game_id' => $gameId,
            'from_moment_key' => $currentMoment->moment_key,
            'target_moment_key' => $targetKey,
            'reason' => $rejection,
        ]);

        if ($rejection === null) {
            return ['target' => $target, 'candidate' => null, 'confirmed' => false];
        }

        return ['target' => null, 'candidate' => $confirmable ? $targetKey : null, 'confirmed' => false];
    }

    /**
     * Read the counters carried in the per-moment footnote blob.
     *
     * The blob is rewritten every turn and keyed by the moment it belongs to, so
     * both counters reset naturally when the pointer moves to a new room.
     *
     * @return array{detour_beats_used:int, ending_blocked_turns:int}
     */
    private function carriedMomentState(Game $game, PlayableMoment $currentMoment): array
    {
        $carried = (array) ($game->moment_footnote ?? []);

        if (($carried['moment_key'] ?? null) !== $currentMoment->moment_key) {
            return ['detour_beats_used' => 0, 'ending_blocked_turns' => 0, 'choice_blocked_turns' => 0, 'catch_up_candidate' => null];
        }

        $candidate = $carried['catch_up_candidate'] ?? null;

        return [
            'detour_beats_used' => max(0, (int) ($carried['detour_beats_used'] ?? 0)),
            'ending_blocked_turns' => max(0, (int) ($carried['ending_blocked_turns'] ?? 0)),
            'choice_blocked_turns' => max(0, (int) ($carried['choice_blocked_turns'] ?? 0)),
            'catch_up_candidate' => is_string($candidate) && $candidate !== '' ? $candidate : null,
        ];
    }

    /**
     * Authored branching choices bound to this card that the player has not yet
     * taken. A card carrying one is a decision point: leaving it without the
     * choice skips the branch the story was built around.
     *
     * @param  Collection<int, SessionAdaptation>  $sessionAdaptations
     * @return array<int, string>
     */
    private function unresolvedBranchingChoiceIds(
        PlayableMoment $moment,
        Collection $sessionAdaptations,
        int $sessionNumber,
        ?string $committedChoiceId,
    ): array {
        $session = $sessionAdaptations->firstWhere('session_number', $sessionNumber);
        $branchingIds = collect((array) data_get($session?->session_choice_design, 'branching_choices', []))
            ->map(static fn ($choice): string => mb_strtolower((string) data_get($choice, 'choice_id', '')))
            ->filter()
            ->all();

        $pending = array_values(array_filter(
            array_map(static fn ($id): string => (string) $id, (array) data_get($moment->scene_card, 'choice_ids', [])),
            static fn (string $id): bool => $id !== '' && in_array(mb_strtolower($id), $branchingIds, true),
        ));

        $committed = mb_strtolower(mb_trim((string) $committedChoiceId));
        foreach ($pending as $id) {
            if ($committed !== '' && mb_strtolower($id) === $committed) {
                return [];
            }
        }

        return $pending;
    }

    /**
     * Detour beats measure distance from the spine, not time spent in a room.
     *
     * Staying in the active room to deepen it costs nothing — only stepping away
     * from it does, and returning to the spine restores the full allowance so a
     * player is never taxed for having improvised earlier.
     */
    private function nextDetourBeats(int $carried, string $routeRelation, bool $pointerAdvanced): int
    {
        if ($pointerAdvanced) {
            return 0;
        }

        return match ($routeRelation) {
            'detoured' => $carried + 1,
            'advanced', 'reconverged' => 0,
            default => $carried,
        };
    }

    /**
     * Last-moment authored choice_ids must resolve before session complete.
     * Empty choice_ids on the final card means no authored gate.
     */
    private function authoredSessionEndingChoiceResolved(
        PlayableMoment $finalMoment,
        ?string $committedChoiceId,
    ): bool {
        $required = array_values(array_filter(
            array_map(static fn ($id): string => (string) $id, (array) data_get($finalMoment->scene_card, 'choice_ids', [])),
            static fn (string $id): bool => $id !== '',
        ));

        if ($required === []) {
            return true;
        }

        $committed = mb_trim((string) $committedChoiceId);

        return $committed !== '' && in_array($committed, $required, true);
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
            $pathLabel = mb_strtoupper((string) ($choice['canonical_path_label'] ?? ''));

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
                ->map(fn ($label): string => mb_strtoupper((string) $label))
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

    private function boundedFootnote(string $footnote, string $gameId): string
    {
        $footnote = mb_trim($footnote);
        if ($footnote === '') {
            Log::channel('narration')->warning('boiler.moment_footnote_missing_on_commit', [
                'game_id' => $gameId,
            ]);

            return '';
        }

        $wordCount = count(preg_split('/\s+/u', $footnote, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if ($wordCount > 80) {
            Log::channel('narration')->info('boiler.moment_footnote_long', [
                'game_id' => $gameId,
                'word_count' => $wordCount,
            ]);
        }

        return $footnote;
    }
}
