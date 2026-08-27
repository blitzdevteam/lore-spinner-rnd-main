<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Actions\Game\ProcessGameOpeningAction;
use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Events\MultiplayerArbiterStateChanged;
use App\Models\Game;
use App\Models\MultiplayerLobby;
use App\Models\Prompt;
use App\Services\ChaosEngineService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessMultiplayerLobbyNextSessionAction
{
    public function __construct(
        private readonly ProcessGameOpeningAction $processGameOpening,
        private readonly ChaosEngineService $engine,
    ) {}

    public function handle(string $lobbyId): void
    {
        $lobby = MultiplayerLobby::query()->find($lobbyId);

        if (
            $lobby === null
            || $lobby->status !== MultiplayerLobbyStatusEnum::Started
            || $lobby->turn_status !== MultiplayerArbiterTurnStatusEnum::Opening
            || $lobby->game_id === null
        ) {
            return;
        }

        $game = Game::query()
            ->with([
                'story' => fn (BelongsTo $query) => $query->with([
                    'adaptation.sessionAdaptations',
                ]),
            ])
            ->find($lobby->game_id);

        if ($game === null || ! $game->current_session_complete || $game->story === null) {
            return;
        }

        $story = $game->story;
        $currentSessionNumber = (int) $game->current_session_number;
        $nextSessionNumber = $currentSessionNumber + 1;
        $totalSessions = (int) ($story->adaptation?->sessionAdaptations?->count() ?? 0);

        if ($nextSessionNumber > $totalSessions) {
            return;
        }

        $arcRow = $this->findArcProgressionRow($story->adaptation, $nextSessionNumber);
        $openingHandoff = (string) ($arcRow['opens_with'] ?? '');
        $resolvedConsequencePath = null;

        if (! empty($game->defining_choice_id) && ! empty($game->defining_path_label)) {
            $priorSessionNumber = $this->engine->extractSessionNumber($game->defining_choice_id);
            $priorSession = $story->adaptation?->sessionAdaptations
                ?->firstWhere('session_number', $priorSessionNumber);

            if ($priorSession !== null && ! empty($priorSession->choice_consequence_map)) {
                $resolvedConsequencePath = $this->engine->resolveConsequencePath(
                    $game->defining_choice_id,
                    $game->defining_path_label,
                    $priorSession->choice_consequence_map,
                );
            }
        }

        if (! empty($resolvedConsequencePath['next_session_opening'])) {
            $openingHandoff = $resolvedConsequencePath['next_session_opening'];
        }

        $nextSession = $story->adaptation?->sessionAdaptations
            ?->firstWhere('session_number', $nextSessionNumber);

        if ($story->adaptation?->boiler_enabled && $nextSession === null) {
            return;
        }

        $boilerStateBeforeTransition = [
            'current_session_number' => $game->current_session_number,
            'current_session_complete' => $game->current_session_complete,
            'current_playable_session_cut_id' => $game->current_playable_session_cut_id,
            'current_playable_moment_id' => $game->current_playable_moment_id,
            'playable_moment_turn_count' => $game->playable_moment_turn_count,
            'moment_footnote' => $game->moment_footnote,
        ];

        try {
            $this->processGameOpening->handleNextSession(
                $game->id,
                $nextSessionNumber,
                $openingHandoff,
                $resolvedConsequencePath,
                $boilerStateBeforeTransition,
            );
        } catch (Throwable $exception) {
            Log::channel('narration')->error('multiplayer.arbiter.next_session_failed', [
                'lobby_id' => $lobby->id,
                'game_id' => $game->id,
                'session_number' => $nextSessionNumber,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        $sourcePrompt = Prompt::query()
            ->where('game_id', $game->id)
            ->where('session_number', $nextSessionNumber)
            ->whereNull('prompt')
            ->orderByDesc('id')
            ->first();

        if ($sourcePrompt !== null && $sourcePrompt->choices !== []) {
            $sourcePrompt->forceFill(['choices' => []])->save();
        }

        $updatedLobby = DB::transaction(function () use ($lobby, $sourcePrompt): ?MultiplayerLobby {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->find($lobby->id);

            if (
                $lockedLobby === null
                || ! in_array($lockedLobby->status, [
                    MultiplayerLobbyStatusEnum::Started,
                    MultiplayerLobbyStatusEnum::Paused,
                ], true)
                || $lockedLobby->turn_status !== MultiplayerArbiterTurnStatusEnum::Opening
                || $lockedLobby->current_source_prompt_id !== null
            ) {
                return null;
            }

            if ($sourcePrompt === null) {
                return $lockedLobby;
            }

            $updatedGame = Game::query()->find($lockedLobby->game_id);

            $lockedLobby->update([
                'current_source_prompt_id' => $sourcePrompt->id,
                'turn_status' => $updatedGame?->current_session_complete
                    ? MultiplayerArbiterTurnStatusEnum::SessionComplete
                    : MultiplayerArbiterTurnStatusEnum::CollectingProposals,
                'decision_deadline_at' => null,
                'decision_remaining_seconds' => null,
            ]);

            return $lockedLobby;
        });

        if ($updatedLobby !== null) {
            MultiplayerArbiterStateChanged::dispatch($updatedLobby);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function findArcProgressionRow(mixed $adaptation, int $sessionNumber): array
    {
        $storyMap = (array) ($adaptation?->story_session_map ?? []);

        foreach ((array) ($storyMap['arc_progression'] ?? []) as $row) {
            if ((int) ($row['session_number'] ?? 0) === $sessionNumber) {
                return (array) $row;
            }
        }

        return [];
    }
}
