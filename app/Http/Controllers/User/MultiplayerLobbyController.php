<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Actions\Multiplayer\AdvanceMultiplayerLobbySessionAction;
use App\Actions\Multiplayer\ChooseMultiplayerLobbyRouletteChoiceAction;
use App\Actions\Multiplayer\CreateMultiplayerLobbyAction;
use App\Actions\Multiplayer\JoinMultiplayerLobbyAction;
use App\Actions\Multiplayer\LeaveMultiplayerLobbyAction;
use App\Actions\Multiplayer\ProcessMultiplayerLobbyNextSessionAction;
use App\Actions\Multiplayer\ProcessMultiplayerLobbyOpeningAction;
use App\Actions\Multiplayer\ProcessMultiplayerLobbyTurnAction;
use App\Actions\Multiplayer\ReconcileMultiplayerLobbyPauseAction;
use App\Actions\Multiplayer\RecordMultiplayerLobbyHeartbeatAction;
use App\Actions\Multiplayer\ResolveMultiplayerLobbyDecisionDeadlineAction;
use App\Actions\Multiplayer\ResolveMultiplayerLobbyGuestAction;
use App\Actions\Multiplayer\SelectMultiplayerLobbyProposalAction;
use App\Actions\Multiplayer\SendMultiplayerLobbyChatMessageAction;
use App\Actions\Multiplayer\StartMultiplayerLobbyAction;
use App\Actions\Multiplayer\SubmitMultiplayerLobbyProposalAction;
use App\Actions\Multiplayer\UpdateMultiplayerLobbyReadinessAction;
use App\Actions\Multiplayer\UpdateMultiplayerLobbySettingsAction;
use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Exceptions\Multiplayer\MultiplayerLobbyJoinException;
use App\Exceptions\Multiplayer\MultiplayerLobbyStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Multiplayer\JoinMultiplayerLobbyRequest;
use App\Http\Requests\User\Multiplayer\StoreMultiplayerLobbyChatMessageRequest;
use App\Http\Requests\User\Multiplayer\StoreMultiplayerLobbyProposalRequest;
use App\Http\Requests\User\Multiplayer\StoreMultiplayerLobbyRequest;
use App\Http\Requests\User\Multiplayer\StoreMultiplayerLobbyRouletteChoiceRequest;
use App\Http\Requests\User\Multiplayer\UpdateMultiplayerLobbyReadinessRequest;
use App\Http\Requests\User\Multiplayer\UpdateMultiplayerLobbySettingsRequest;
use App\Http\Resources\MultiplayerArbiterStateResource;
use App\Http\Resources\MultiplayerLobbyChatMessageResource;
use App\Models\MultiplayerLobby;
use App\Models\MultiplayerLobbyProposal;
use App\Models\Story;
use App\Models\User;
use App\Support\StoryPlayability;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

final class MultiplayerLobbyController extends Controller
{
    public function index(): Response
    {
        $rooms = MultiplayerLobby::query()
            ->where('status', MultiplayerLobbyStatusEnum::Waiting)
            ->with([
                'story' => function (BelongsTo $query): void {
                    $query->with('media');
                },
                'members' => function (HasMany $query): void {
                    $query
                        ->with('user')
                        ->orderBy('joined_at');
                },
            ])
            ->latest()
            ->get()
            ->filter(static fn (MultiplayerLobby $lobby): bool => $lobby->members->count() < $lobby->max_players)
            ->values()
            ->toResourceCollection();

        return inertia('Multiplayer/Library', [
            'rooms' => $rooms,
        ]);
    }

    public function store(
        #[CurrentUser] User $user,
        Story $story,
        StoreMultiplayerLobbyRequest $request,
        CreateMultiplayerLobbyAction $createMultiplayerLobby,
    ): RedirectResponse {
        if (! $this->isStoryAvailable($story)) {
            return back()->with('error', 'This story is not available for multiplayer yet.');
        }

        $lobby = $createMultiplayerLobby->handle(
            $user,
            $story,
            MultiplayerLobbyModeEnum::from($request->string('mode')->toString()),
            $request->integer('max_players'),
            $request->validated('settings'),
        );

        return to_route('multiplayer.lobbies.show', ['lobby' => $lobby->room_code]);
    }

    public function show(
        #[CurrentUser] User $user,
        MultiplayerLobby $lobby,
        JoinMultiplayerLobbyAction $joinMultiplayerLobby,
        ReconcileMultiplayerLobbyPauseAction $reconcileMultiplayerLobbyPause,
    ): Response {
        Gate::forUser($user)->authorize('view', $lobby);

        $lobby = $joinMultiplayerLobby->handle($user, $lobby);

        if ($reconcileMultiplayerLobbyPause->handle($lobby)) {
            $lobby->refresh();
        }

        $lobby->load([
            'story' => function (BelongsTo $query): void {
                $query
                    ->with([
                        'category:id,title',
                        'creator:id,first_name,last_name,username,avatar',
                        'media',
                    ])
                    ->withCount('chapters');
            },
            'host',
            'members' => function (HasMany $query): void {
                $query
                    ->with('user')
                    ->orderBy('joined_at');
            },
        ]);

        if (in_array($lobby->status, [
            MultiplayerLobbyStatusEnum::Started,
            MultiplayerLobbyStatusEnum::Paused,
            MultiplayerLobbyStatusEnum::Completed,
        ], true)) {
            $lobby->load('game');
            $lobby->game?->load([
                'story' => fn (BelongsTo $query) => $query->with([
                    'adaptation' => fn (HasOne $adaptationQuery) => $adaptationQuery
                        ->withCount('sessionAdaptations')
                        ->with([
                            'sessionAdaptations' => fn (HasMany $sessionAdaptationsQuery) => $sessionAdaptationsQuery
                                ->where('session_number', 1)
                                ->select(['id', 'story_adaptation_id', 'session_number', 'entry_point_diagnosis']),
                        ]),
                ]),
                'prompts' => fn (HasMany $query) => $query
                    ->select(['id', 'game_id', 'source_prompt_id', 'session_number', 'response', 'choices', 'prompt'])
                    ->orderBy('id'),
            ]);

            return inertia('Arbiter', [
                'lobby' => $lobby->toResource(),
                'game' => $lobby->game?->toResource(),
                'arbiter' => (new MultiplayerArbiterStateResource($lobby))->resolve(request()),
                'chatMessages' => MultiplayerLobbyChatMessageResource::collection(
                    $lobby->chatMessages()
                        ->with('user')
                        ->latest('created_at')
                        ->latest('id')
                        ->limit(100)
                        ->get()
                        ->reverse()
                        ->values(),
                )->resolve(request()),
            ]);
        }

        return inertia('Multiplayer/Lobby', [
            'lobby' => $lobby->toResource(),
        ]);
    }

    public function join(
        JoinMultiplayerLobbyRequest $request,
        JoinMultiplayerLobbyAction $joinMultiplayerLobby,
        ResolveMultiplayerLobbyGuestAction $resolveMultiplayerLobbyGuest,
    ): RedirectResponse {
        $lobby = MultiplayerLobby::query()
            ->where('room_code', $request->string('room_code')->toString())
            ->first();

        if ($lobby === null) {
            throw ValidationException::withMessages([
                'room_code' => 'We could not find a lobby with that room code.',
            ]);
        }

        $user = $request->user('user');

        if (! $user instanceof User) {
            $user = $resolveMultiplayerLobbyGuest->handle(
                $request->string('email')->toString(),
                $request->string('first_name')->toString(),
                $request->string('last_name')->toString(),
            );

            auth('user')->login($user, remember: true);
            $request->session()->regenerate();
        }

        try {
            $lobby = $joinMultiplayerLobby->handle($user, $lobby);
        } catch (MultiplayerLobbyJoinException $exception) {
            throw ValidationException::withMessages([
                'room_code' => $exception->getMessage(),
            ]);
        }

        return to_route('multiplayer.lobbies.show', ['lobby' => $lobby->room_code]);
    }

    public function destroy(
        #[CurrentUser] User $user,
        MultiplayerLobby $lobby,
        LeaveMultiplayerLobbyAction $leaveMultiplayerLobby,
    ): RedirectResponse {
        Gate::forUser($user)->authorize('view', $lobby);

        $lobby->load('story:id,slug');
        $storySlug = $lobby->story->slug;

        $isGameplayPaused = $leaveMultiplayerLobby->handle($user, $lobby);

        return to_route('stories.multiplayer', [
            'story' => $storySlug,
            ...($isGameplayPaused ? ['room' => $lobby->room_code] : []),
        ])->with(
            'success',
            $isGameplayPaused
                ? 'You left the story. It is paused until you rejoin the room.'
                : 'You have left the lobby.',
        );
    }

    public function heartbeat(
        #[CurrentUser] User $user,
        MultiplayerLobby $lobby,
        RecordMultiplayerLobbyHeartbeatAction $recordMultiplayerLobbyHeartbeat,
        ResolveMultiplayerLobbyDecisionDeadlineAction $resolveMultiplayerLobbyDecisionDeadline,
    ): HttpResponse {
        Gate::forUser($user)->authorize('view', $lobby);

        if ($recordMultiplayerLobbyHeartbeat->handle($user, $lobby)) {
            $lobby->refresh();

            return response()->noContent(
                $lobby->status === MultiplayerLobbyStatusEnum::Closed
                    ? HttpResponse::HTTP_GONE
                    : HttpResponse::HTTP_CONFLICT,
            );
        }

        $lobby->refresh();

        if (
            $lobby->mode !== MultiplayerLobbyModeEnum::Roulette
            || $lobby->status !== MultiplayerLobbyStatusEnum::Started
            || $lobby->turn_status !== MultiplayerArbiterTurnStatusEnum::AwaitingRouletteChoice
            || $lobby->current_source_prompt_id === null
            || $lobby->decision_deadline_at === null
            || $lobby->decision_deadline_at->isAfter(now())
        ) {
            return response()->noContent();
        }

        $selection = $resolveMultiplayerLobbyDecisionDeadline->handle(
            $lobby->id,
            $lobby->current_source_prompt_id,
            $lobby->decision_deadline_at->getTimestamp(),
        );

        if ($selection !== null) {
            $lobbyId = $selection['lobby']->id;
            $proposalId = $selection['proposal_id'];

            dispatch(static function () use ($lobbyId, $proposalId): void {
                app(ProcessMultiplayerLobbyTurnAction::class)->handle($lobbyId, $proposalId);
            })->afterResponse();
        }

        return response()->noContent();
    }

    public function updateReadiness(
        #[CurrentUser] User $user,
        MultiplayerLobby $lobby,
        UpdateMultiplayerLobbyReadinessRequest $request,
        UpdateMultiplayerLobbyReadinessAction $updateMultiplayerLobbyReadiness,
    ): RedirectResponse {
        Gate::forUser($user)->authorize('view', $lobby);

        try {
            $lobby = $updateMultiplayerLobbyReadiness->handle($user, $lobby, $request->boolean('is_ready'));
        } catch (MultiplayerLobbyStateException $exception) {
            throw ValidationException::withMessages([
                'lobby' => $exception->getMessage(),
            ]);
        }

        return to_route('multiplayer.lobbies.show', ['lobby' => $lobby->room_code]);
    }

    public function updateSettings(
        #[CurrentUser] User $user,
        MultiplayerLobby $lobby,
        UpdateMultiplayerLobbySettingsRequest $request,
        UpdateMultiplayerLobbySettingsAction $updateMultiplayerLobbySettings,
    ): RedirectResponse {
        Gate::forUser($user)->authorize('updateSettings', $lobby);

        try {
            $lobby = $updateMultiplayerLobbySettings->handle(
                $user,
                $lobby,
                $request->validated('settings'),
            );
        } catch (MultiplayerLobbyStateException $exception) {
            throw ValidationException::withMessages([
                'lobby' => $exception->getMessage(),
            ]);
        }

        return to_route('multiplayer.lobbies.show', ['lobby' => $lobby->room_code]);
    }

    public function start(
        #[CurrentUser] User $user,
        MultiplayerLobby $lobby,
        StartMultiplayerLobbyAction $startMultiplayerLobby,
    ): RedirectResponse {
        Gate::forUser($user)->authorize('start', $lobby);

        try {
            $lobby = $startMultiplayerLobby->handle($user, $lobby);
        } catch (MultiplayerLobbyStateException $exception) {
            throw ValidationException::withMessages([
                'lobby' => $exception->getMessage(),
            ]);
        }

        $lobbyId = $lobby->id;

        dispatch(static function () use ($lobbyId): void {
            app(ProcessMultiplayerLobbyOpeningAction::class)->handle($lobbyId);
        })->afterResponse();

        return to_route('multiplayer.lobbies.show', ['lobby' => $lobby->room_code]);
    }

    public function submitProposal(
        #[CurrentUser] User $user,
        MultiplayerLobby $lobby,
        StoreMultiplayerLobbyProposalRequest $request,
        SubmitMultiplayerLobbyProposalAction $submitMultiplayerLobbyProposal,
    ): RedirectResponse {
        Gate::forUser($user)->authorize('view', $lobby);

        try {
            $submitMultiplayerLobbyProposal->handle(
                $user,
                $lobby,
                $request->string('proposal')->toString(),
            );
        } catch (MultiplayerLobbyStateException $exception) {
            throw ValidationException::withMessages([
                'lobby' => $exception->getMessage(),
            ]);
        }

        return to_route('multiplayer.lobbies.show', ['lobby' => $lobby->room_code]);
    }

    public function chooseRouletteChoice(
        #[CurrentUser] User $user,
        MultiplayerLobby $lobby,
        StoreMultiplayerLobbyRouletteChoiceRequest $request,
        ChooseMultiplayerLobbyRouletteChoiceAction $chooseMultiplayerLobbyRouletteChoice,
    ): RedirectResponse {
        Gate::forUser($user)->authorize('view', $lobby);

        try {
            $selection = $chooseMultiplayerLobbyRouletteChoice->handle(
                $user,
                $lobby,
                $request->has('choice_index') ? $request->integer('choice_index') : null,
                $request->has('proposal') ? $request->string('proposal')->toString() : null,
            );
        } catch (MultiplayerLobbyStateException $exception) {
            throw ValidationException::withMessages([
                'lobby' => $exception->getMessage(),
            ]);
        }

        $lobby = $selection['lobby'];
        $lobbyId = $lobby->id;
        $selectionId = $selection['proposal_id'];

        dispatch(static function () use ($lobbyId, $selectionId): void {
            app(ProcessMultiplayerLobbyTurnAction::class)->handle($lobbyId, $selectionId);
        })->afterResponse();

        return to_route('multiplayer.lobbies.show', ['lobby' => $lobby->room_code]);
    }

    public function sendChatMessage(
        #[CurrentUser] User $user,
        MultiplayerLobby $lobby,
        StoreMultiplayerLobbyChatMessageRequest $request,
        SendMultiplayerLobbyChatMessageAction $sendMultiplayerLobbyChatMessage,
    ): RedirectResponse {
        Gate::forUser($user)->authorize('view', $lobby);

        try {
            $sendMultiplayerLobbyChatMessage->handle(
                $user,
                $lobby,
                $request->string('message')->toString(),
            );
        } catch (MultiplayerLobbyStateException $exception) {
            throw ValidationException::withMessages([
                'message' => $exception->getMessage(),
            ]);
        }

        return to_route('multiplayer.lobbies.show', ['lobby' => $lobby->room_code]);
    }

    public function selectProposal(
        #[CurrentUser] User $user,
        MultiplayerLobby $lobby,
        MultiplayerLobbyProposal $proposal,
        SelectMultiplayerLobbyProposalAction $selectMultiplayerLobbyProposal,
    ): RedirectResponse {
        Gate::forUser($user)->authorize('view', $lobby);

        try {
            $lobby = $selectMultiplayerLobbyProposal->handle($user, $lobby, $proposal);
        } catch (MultiplayerLobbyStateException $exception) {
            throw ValidationException::withMessages([
                'lobby' => $exception->getMessage(),
            ]);
        }

        $lobbyId = $lobby->id;
        $proposalId = $proposal->id;

        dispatch(static function () use ($lobbyId, $proposalId): void {
            app(ProcessMultiplayerLobbyTurnAction::class)->handle($lobbyId, $proposalId);
        })->afterResponse();

        return to_route('multiplayer.lobbies.show', ['lobby' => $lobby->room_code]);
    }

    public function advanceSession(
        #[CurrentUser] User $user,
        MultiplayerLobby $lobby,
        AdvanceMultiplayerLobbySessionAction $advanceMultiplayerLobbySession,
    ): RedirectResponse {
        Gate::forUser($user)->authorize('start', $lobby);

        try {
            $lobby = $advanceMultiplayerLobbySession->handle($user, $lobby);
        } catch (MultiplayerLobbyStateException $exception) {
            throw ValidationException::withMessages([
                'lobby' => $exception->getMessage(),
            ]);
        }

        if (
            $lobby->status === MultiplayerLobbyStatusEnum::Started
            && $lobby->turn_status === MultiplayerArbiterTurnStatusEnum::Opening
        ) {
            $lobbyId = $lobby->id;

            dispatch(static function () use ($lobbyId): void {
                app(ProcessMultiplayerLobbyNextSessionAction::class)->handle($lobbyId);
            })->afterResponse();
        }

        return to_route('multiplayer.lobbies.show', ['lobby' => $lobby->room_code]);
    }

    private function isStoryAvailable(Story $story): bool
    {
        return StoryPlayability::isPlayable($story);
    }
}
