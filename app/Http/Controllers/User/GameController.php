<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Actions\Game\CreateGameAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Game\StoreGameRequest;
use App\Models\Game;
use App\Models\GameCompletion;
use App\Models\GameSessionCompletion;
use App\Models\Story;
use App\Models\User;
use App\Models\UserActivityDay;
use App\Services\ChaosEngineService;
use App\Services\Adaptation\StoryGameResetService;
use App\Services\Runtime\NarrationResultNormalizer;
use App\Services\Runtime\PlayableMomentResolver;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Response;
use Throwable;

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
        private readonly PlayableMomentResolver $momentResolver,
        private readonly NarrationResultNormalizer $resultNormalizer,
        private readonly StoryGameResetService $gameResetService,
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
     */
    public function begin(Game $game): RedirectResponse
    {
        if ($game->prompts()->exists()) {
            return to_route('user.games.show', $game);
        }

        $story = $game->story()->with(['adaptation', 'adaptation.sessionAdaptations'])->first();
        $firstSession = $story->adaptation?->sessionAdaptations?->firstWhere('session_number', 1);

        if ($story->adaptation?->boiler_enabled) {
            if ($firstSession === null) {
                return to_route('user.games.show', $game)
                    ->with('error', 'This Boiler story has no first session adaptation.');
            }
            $this->momentResolver->initializeForSession($game, $firstSession);
            $game->refresh();
        }

        $sessionContext = $this->engine->loadSessionContext($story, 1, null, game: $game);

        if ($sessionContext === null) {
            return to_route('user.games.show', $game)
                ->with('error', 'This story has not been adapted yet. Re-run the adaptation pipeline before starting.');
        }

        $emptyWorldState = $this->engine->emptyWorldState();
        $emptyAlignment = $this->engine->emptyAlignmentScaffold();

        $systemPrompt = $this->engine->renderSystemPrompt(
            sessionContext:      $sessionContext,
            worldState:          $emptyWorldState,
            alignmentScaffold:   $emptyAlignment,
            symbolicMemory:      null,
            isSessionStart:      true,
            isClimacticPrevious: false,
            isClimacticThisTurn: false,
        );

        try {
            $protagonist = (string) ($story->system_prompt['character_name'] ?? 'the protagonist');

            $result = $this->engine->callAgent(
                model: $game->model,
                systemPrompt: $systemPrompt,
                conversationHistory: [],
                playerAction: null,
                protagonist: $protagonist,
                temperature: $this->engine->gameTemperatureFor($game->model),
                game: $game,
                isOpening: true,
            );
            $normalizedChoices = $this->resultNormalizer->choices(
                $result,
                $game->current_playable_moment_id !== null,
            );
            $normalizedChoices = $this->momentResolver->sanitizeSuggestionsForCurrentMoment(
                $game,
                $normalizedChoices,
                $story->adaptation->sessionAdaptations,
            );

            $worldState = $this->engine->mergeStateDelta($emptyWorldState, $result['state_delta']);
            $alignmentScaffold = $this->engine->mergeAlignmentDelta($emptyAlignment, $result['alignment_tally_delta']);
            $symbolicMemory = $this->engine->appendMemory(null, $result['symbolic_memory_update']);

            $branchCommit = $this->engine->resolveBranchCommit(
                $result,
                $game,
                $story->adaptation?->sessionAdaptations,
            );

            $game->update([
                'world_state'              => $worldState,
                'alignment_scaffold'       => $alignmentScaffold,
                'symbolic_memory'          => $symbolicMemory,
                'is_climactic_choice'      => (bool) $result['is_climactic_choice'],
                'defining_choice_id'       => $branchCommit ? $branchCommit['defining_choice_id']   : null,
                'defining_path_label'      => $branchCommit ? $branchCommit['defining_path_label']  : null,
                'defining_choice_line'     => $branchCommit ? $branchCommit['defining_choice_line'] : null,
                'current_session_complete' => $game->current_playable_moment_id !== null
                    ? false
                    : $result['session_complete'],
            ]);

            $game->prompts()->create([
                'session_number' => 1,
                'response' => $result['response'],
                'choices' => $normalizedChoices,
            ]);

            GameSessionCompletion::updateOrCreate(
                [
                    'game_id'            => $game->id,
                    'story_cycle_number' => $game->current_story_cycle_number,
                    'session_number'     => 1,
                ],
                [
                    'story_id'     => $story->id,
                    'user_id'      => $game->user_id,
                    'started_at'   => now(),
                    'completed_at' => null,
                ],
            );

            UserActivityDay::record($game->user_id);

            Log::channel('narration')->info('game.begin', [
                'game_id' => $game->id,
                'story_id' => $story->id,
                'model' => $game->model,
                'session_complete' => $result['session_complete'],
                'response_bytes' => mb_strlen($result['response']),
            ]);

            return to_route('user.games.show', $game);
        } catch (Throwable $e) {
            Log::channel('narration')->error('game.begin_failed', [
                'game_id' => $game->id,
                'story_id' => $story->id,
                'model' => $game->model,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return to_route('user.games.show', $game)
                ->with('error', 'Opening narration hiccuped — please retry.');
        }
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

        // Load authored close design for the next session.
        $nextSA = $story->adaptation?->sessionAdaptations
            ?->firstWhere('session_number', $nextSessionNumber);
        $sessionCloseDesign = $nextSA?->session_close_design;
        $boilerStateBeforeTransition = [
            'current_session_number' => $game->current_session_number,
            'current_session_complete' => $game->current_session_complete,
            'current_playable_session_cut_id' => $game->current_playable_session_cut_id,
            'current_playable_moment_id' => $game->current_playable_moment_id,
            'playable_moment_turn_count' => $game->playable_moment_turn_count,
            'moment_footnote' => $game->moment_footnote,
        ];

        try {
            if ($story->adaptation?->boiler_enabled) {
                if ($nextSA === null) {
                    throw new \RuntimeException('The next Boiler session has not been finalized.');
                }
                $this->momentResolver->initializeForSession($game, $nextSA);
                $game->refresh();
            }

            $sessionContext = $this->engine->loadSessionContext(
                story:                   $story,
                sessionNumber:           $nextSessionNumber,
                openingHandoff:          $openingHandoff,
                resolvedConsequencePath: $resolvedConsequencePath,
                sessionCloseDesign:      $sessionCloseDesign,
                game:                    $game,
            );

            if ($sessionContext === null) {
                throw new \RuntimeException('The next session has not been adapted yet.');
            }

            $carriedWorldState = $game->world_state ?? $this->engine->emptyWorldState();
            $carriedAlignment  = $game->alignment_scaffold ?? $this->engine->emptyAlignmentScaffold();
            $carriedMemory     = $game->symbolic_memory;

            $systemPrompt = $this->engine->renderSystemPrompt(
                sessionContext:      $sessionContext,
                worldState:          $carriedWorldState,
                alignmentScaffold:   $carriedAlignment,
                symbolicMemory:      $carriedMemory,
                isSessionStart:      true,
                isClimacticPrevious: false,
                isClimacticThisTurn: false,
            );

            $protagonist = (string) ($story->system_prompt['character_name'] ?? 'the protagonist');

            $result = $this->engine->callAgent(
                model: $game->model,
                systemPrompt: $systemPrompt,
                conversationHistory: [],
                playerAction: null,
                protagonist: $protagonist,
                temperature: $this->engine->gameTemperatureFor($game->model),
                worldState: $carriedWorldState,
                game: $game,
                isOpening: true,
            );
            $normalizedChoices = $this->resultNormalizer->choices(
                $result,
                $game->current_playable_moment_id !== null,
            );
            $normalizedChoices = $this->momentResolver->sanitizeSuggestionsForCurrentMoment(
                $game,
                $normalizedChoices,
                $story->adaptation->sessionAdaptations,
            );

            $worldState = $this->engine->mergeStateDelta($carriedWorldState, $result['state_delta']);
            $alignmentScaffold = $this->engine->mergeAlignmentDelta($carriedAlignment, $result['alignment_tally_delta']);
            $symbolicMemory = $this->engine->appendMemory($carriedMemory, $result['symbolic_memory_update']);

            $branchCommit = $this->engine->resolveBranchCommit(
                $result,
                $game,
                $story->adaptation?->sessionAdaptations,
            );

            $game->update([
                'current_session_number'   => $nextSessionNumber,
                'current_session_complete' => $game->current_playable_moment_id !== null
                    ? false
                    : $result['session_complete'],
                'world_state'              => $worldState,
                'alignment_scaffold'       => $alignmentScaffold,
                'symbolic_memory'          => $symbolicMemory,
                'is_climactic_choice'      => (bool) $result['is_climactic_choice'],
                'defining_choice_id'       => $branchCommit ? $branchCommit['defining_choice_id']   : $game->defining_choice_id,
                'defining_path_label'      => $branchCommit ? $branchCommit['defining_path_label']  : $game->defining_path_label,
                'defining_choice_line'     => $branchCommit ? $branchCommit['defining_choice_line'] : $game->defining_choice_line,
            ]);

            $game->prompts()->create([
                'session_number' => $nextSessionNumber,
                'response' => $result['response'],
                'choices' => $normalizedChoices,
            ]);

            GameSessionCompletion::where('game_id', $game->id)
                ->where('story_cycle_number', $game->current_story_cycle_number)
                ->where('session_number', $currentSessionNumber)
                ->update(['completed_at' => now()]);

            GameSessionCompletion::updateOrCreate(
                [
                    'game_id'            => $game->id,
                    'story_cycle_number' => $game->current_story_cycle_number,
                    'session_number'     => $nextSessionNumber,
                ],
                [
                    'story_id'     => $story->id,
                    'user_id'      => $game->user_id,
                    'started_at'   => now(),
                    'completed_at' => null,
                ],
            );

            UserActivityDay::record($game->user_id);

            Log::channel('narration')->info('game.next_session', [
                'game_id' => $game->id,
                'story_id' => $story->id,
                'session_number' => $nextSessionNumber,
                'model' => $game->model,
                'session_complete' => $result['session_complete'],
            ]);

            return to_route('user.games.show', $game);
        } catch (Throwable $e) {
            if ($story->adaptation?->boiler_enabled) {
                $game->update($boilerStateBeforeTransition);
            }

            Log::channel('narration')->error('game.next_session_failed', [
                'game_id' => $game->id,
                'session_number' => $nextSessionNumber,
                'model' => $game->model,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return to_route('user.games.show', $game)
                ->with('error', 'Could not open the next session — please try again.');
        }
    }

    public function reset(Game $game): RedirectResponse
    {
        $this->gameResetService->reset($game);

        UserActivityDay::record($game->user_id);

        return to_route('user.games.show', $game);
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
