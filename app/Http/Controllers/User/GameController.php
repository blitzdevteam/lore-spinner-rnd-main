<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Actions\Game\CreateGameAction;
use App\Actions\Game\ProcessGameOpeningAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Game\StoreGameRequest;
use App\Models\Game;
use App\Models\GameCompletion;
use App\Models\GameSessionCompletion;
use App\Models\Story;
use App\Models\User;
use App\Models\UserActivityDay;
use App\Services\Adaptation\StoryGameResetService;
use App\Services\ChaosEngineService;
use App\Services\Runtime\GameplayAllowanceResolver;
use App\Services\Runtime\PlayerGameplayUsageSummary;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Response;

final class GameController extends Controller
{
    /**
     * Stories available at launch. All other published stories show "Coming Soon".
     * Update this list when a new story is ready for production.
     *
     * @var array<int, string>
     */
    public const LAUNCH_SLUGS = [
        'the-wonderful-wizard-of-oz',
        'the-adventure-of-the-speckled-band',
        'the-tell-tale-heart',
        'the-masque-of-the-red-death',
        'treasure-island',
        'dr-jekyll-and-mr-hyde',
        'wasteland',
        'pjs',
        'anima-machina',
        'nocturne',
        'i-love-lucy-job-switching',
        'the-matrix',
        'momotaro-the-peach-boy',
    ];

    public function __construct(
        private readonly ChaosEngineService $engine,
        private readonly ProcessGameOpeningAction $openingAction,
        private readonly StoryGameResetService $gameResetService,
        private readonly PlayerGameplayUsageSummary $gameplayUsageSummary,
        private readonly GameplayAllowanceResolver $allowanceResolver,
    ) {}

    public function index(): RedirectResponse
    {
        return to_route('index');
    }

    public function show(Game $game): Response
    {
        $game->load([
            'story' => fn ($q) => $q->with([
                'adaptation' => fn ($a) => $a
                    ->withCount('sessionAdaptations')
                    ->with(['sessionAdaptations' => fn ($sa) => $sa
                        ->where('session_number', 1)
                        ->select(['id', 'story_adaptation_id', 'session_number', 'entry_point_diagnosis']),
                    ]),
            ]),
            'prompts' => fn ($q) => $q
                ->select(['id', 'game_id', 'session_number', 'response', 'choices', 'prompt'])
                ->oldest(),
        ]);

        return inertia('User/Games/Show', [
            'game' => $game->toResource(),
            'usage' => fn (): array => $this->gameplayUsage($game),
        ]);
    }

    public function store(
        #[CurrentUser] User $user,
        StoreGameRequest $request,
        CreateGameAction $createGameAction
    ): RedirectResponse {
        $story = Story::find($request->string('story_id')->toString());

        if ($story === null || ! in_array($story->slug, self::LAUNCH_SLUGS, true)) {
            return back()->with('error', 'This story is not available yet. Stay tuned!');
        }

        $existingGame = $user->games()->whereBelongsTo($story)->first();

        if ($existingGame) {
            return to_route('user.games.show', $existingGame);
        }

        $game = $createGameAction->handle($user, $story);

        return to_route('user.games.show', $game);
    }

    /**
     * Fire the Chaos engine opening narration for a new game.
     * Called automatically via the cinematic opening sequence.
     *
     * Returns immediately; Sonnet runs after the response so proxies never
     * 504 on a silent TTFB (same pattern as player turns).
     */
    public function begin(Game $game): RedirectResponse
    {
        if ($game->prompts()->exists()) {
            return to_route('user.games.show', $game);
        }

        $story = $game->story()->with(['adaptation', 'adaptation.sessionAdaptations'])->first();
        $firstSession = $story->adaptation?->sessionAdaptations?->firstWhere('session_number', 1);

        if ($story->adaptation?->boiler_enabled && $firstSession === null) {
            return to_route('user.games.show', $game)
                ->with('error', 'This Boiler story has no first session adaptation.');
        }

        if (
            ! $story->adaptation?->boiler_enabled
            && $this->engine->loadSessionContext($story, 1, null, game: $game) === null
        ) {
            return to_route('user.games.show', $game)
                ->with('error', 'This story has not been adapted yet. Re-run the adaptation pipeline before starting.');
        }

        $opening = $this->openingAction;
        $gameId = $game->id;
        Cache::put($opening->pendingKey($gameId), 'begin', 180);
        Cache::forget($opening->errorKey($gameId));

        dispatch(function () use ($opening, $gameId): void {
            $opening->handleBegin($gameId);
        })->afterResponse();

        return to_route('user.games.show', $game);
    }

    /**
     * Advance to the next story session when the current session is complete.
     * Carries world_state, symbolic_memory, and alignment_scaffold forward.
     */
    public function nextSession(Game $game): RedirectResponse
    {
        if (! $game->current_session_complete) {
            return to_route('user.games.show', $game);
        }

        $story = $game->story()->with(['adaptation', 'adaptation.sessionAdaptations'])->first();

        $currentSessionNumber = (int) $game->current_session_number;
        $nextSessionNumber    = $currentSessionNumber + 1;
        $totalSessions        = (int) ($story->adaptation?->sessionAdaptations?->count() ?? 0);

        if ($nextSessionNumber > $totalSessions) {
            $now = now();

            GameSessionCompletion::where('game_id', $game->id)
                ->where('story_cycle_number', $game->current_story_cycle_number)
                ->where('session_number', $currentSessionNumber)
                ->update(['completed_at' => $now]);

            $game->update(['completed_at' => $now]);

            GameCompletion::updateOrCreate(
                [
                    'game_id'            => $game->id,
                    'story_cycle_number' => $game->current_story_cycle_number,
                ],
                [
                    'user_id'      => $game->user_id,
                    'story_id'     => $game->story_id,
                    'completed_at' => $now,
                ],
            );

            UserActivityDay::record($game->user_id);

            return to_route('user.games.show', $game)
                ->with('story_complete', true);
        }

        $arcRow         = $this->findArcProgressionRow($story->adaptation, $nextSessionNumber);
        $openingHandoff = (string) ($arcRow['opens_with'] ?? '');

        // Resolve consequence path from the player's last defining choice.
        // If the consequence map has a next_session_opening for the chosen path,
        // it replaces the generic arc_progression opening — it is authored specifically
        // for this player's choice and is always more specific.
        $resolvedConsequencePath = null;
        if (! empty($game->defining_choice_id) && ! empty($game->defining_path_label)) {
            $priorSessionNumber = $this->engine->extractSessionNumber($game->defining_choice_id);
            $priorSA = $story->adaptation?->sessionAdaptations
                ?->firstWhere('session_number', $priorSessionNumber);
            if ($priorSA && ! empty($priorSA->choice_consequence_map)) {
                $resolvedConsequencePath = $this->engine->resolveConsequencePath(
                    $game->defining_choice_id,
                    $game->defining_path_label,
                    $priorSA->choice_consequence_map,
                );
            }
        }

        if (! empty($resolvedConsequencePath['next_session_opening'])) {
            $openingHandoff = $resolvedConsequencePath['next_session_opening'];
        }

        $nextSA = $story->adaptation?->sessionAdaptations
            ?->firstWhere('session_number', $nextSessionNumber);

        if ($story->adaptation?->boiler_enabled && $nextSA === null) {
            return to_route('user.games.show', $game)
                ->with('error', 'The next Boiler session has not been finalized.');
        }

        $boilerStateBeforeTransition = [
            'current_session_number' => $game->current_session_number,
            'current_session_complete' => $game->current_session_complete,
            'current_playable_session_cut_id' => $game->current_playable_session_cut_id,
            'current_playable_moment_id' => $game->current_playable_moment_id,
            'playable_moment_turn_count' => $game->playable_moment_turn_count,
            'moment_footnote' => $game->moment_footnote,
        ];

        $opening = $this->openingAction;
        $gameId = $game->id;
        Cache::put($opening->pendingKey($gameId), 'next_session', 180);
        Cache::forget($opening->errorKey($gameId));

        dispatch(function () use (
            $opening,
            $gameId,
            $nextSessionNumber,
            $openingHandoff,
            $resolvedConsequencePath,
            $boilerStateBeforeTransition,
        ): void {
            $opening->handleNextSession(
                $gameId,
                $nextSessionNumber,
                $openingHandoff,
                $resolvedConsequencePath,
                $boilerStateBeforeTransition,
            );
        })->afterResponse();

        return to_route('user.games.show', $game);
    }

    public function reset(Game $game): RedirectResponse
    {
        $this->gameResetService->reset($game);

        UserActivityDay::record($game->user_id);

        return to_route('user.games.show', $game);
    }

    /**
     * @return array{
     *     turns_played:int,
     *     voice_duration_ms:int,
     *     turns_used:int,
     *     turns_limit:int,
     *     turns_remaining:int,
     *     voice_duration_ms_used:int,
     *     voice_duration_ms_limit:int,
     *     voice_duration_ms_remaining:int,
     * }
     */
    private function gameplayUsage(Game $game): array
    {
        $gameUsage = $this->gameplayUsageSummary->forGame($game->id);
        $accountUsage = $this->gameplayUsageSummary->forUser($game->user_id);
        $allowance = $this->allowanceResolver->forUser($game->user_id);

        return [
            'turns_played' => $gameUsage['turns'],
            'voice_duration_ms' => $gameUsage['voice_duration_ms'],
            'turns_used' => $accountUsage['turns'],
            'turns_limit' => $allowance['turns'],
            'turns_remaining' => max(0, $allowance['turns'] - $accountUsage['turns']),
            'voice_duration_ms_used' => $accountUsage['voice_duration_ms'],
            'voice_duration_ms_limit' => $allowance['voice_duration_ms'],
            'voice_duration_ms_remaining' => max(
                0,
                $allowance['voice_duration_ms'] - $accountUsage['voice_duration_ms'],
            ),
        ];
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
