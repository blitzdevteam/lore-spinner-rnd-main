<?php

declare(strict_types=1);

namespace App\Http\Controllers\Discord;

use App\Actions\Discord\CreateDiscordActivityLobbyAction;
use App\Actions\Discord\ResolveDiscordGuestAction;
use App\Actions\Multiplayer\JoinMultiplayerLobbyAction;
use App\Discord\DiscordClient;
use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use App\Exceptions\Multiplayer\MultiplayerLobbyJoinException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\PrepareDiscordActivityRequest;
use App\Http\Requests\Discord\StartDiscordActivitySessionRequest;
use App\Http\Requests\Discord\StoreDiscordActivityLobbyRequest;
use App\Models\MultiplayerLobby;
use App\Models\Story;
use App\Models\User;
use App\Support\StoryPlayability;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

final class ActivityController extends Controller
{
    public const string SESSION_INSTANCE_KEY = 'discord_activity.instance_id';

    public function __invoke(DiscordClient $discord): Response
    {
        return response()
            ->view('discord.activity', [
                'clientId' => $discord->clientId(),
                'stories' => $this->playableStories(),
                'modes' => [
                    MultiplayerLobbyModeEnum::Arbiter->value => 'Arbiter: one player steers each turn',
                    MultiplayerLobbyModeEnum::Roulette->value => 'Roulette: everyone votes on the choice',
                ],
            ])
            ->header('Content-Security-Policy', PrepareDiscordActivityRequest::FRAME_ANCESTORS);
    }

    public function session(
        StartDiscordActivitySessionRequest $request,
        DiscordClient $discord,
        ResolveDiscordGuestAction $resolveDiscordGuest,
        JoinMultiplayerLobbyAction $joinMultiplayerLobby,
    ): JsonResponse {
        $instanceId = $request->string('instance_id')->toString();
        $accessToken = $discord->exchangeCode($request->string('code')->toString());
        $discordUser = $accessToken === null ? null : $discord->currentUser($accessToken);

        if ($accessToken === null || $discordUser === null) {
            return response()->json(['message' => 'Discord sign-in failed. Close the activity and open it again.'], 401);
        }

        if (! $discord->instanceHasUser($instanceId, $discordUser['id'])) {
            return response()->json(['message' => 'Join the voice channel before opening the activity.'], 403);
        }

        $user = $resolveDiscordGuest->handle($discordUser);

        auth('user')->login($user, remember: true);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_INSTANCE_KEY, $instanceId);

        return response()->json([
            'player' => $user->first_name,
            'csrf_token' => $request->session()->token(),
            ...$this->lobbyPayload($user, $instanceId, $joinMultiplayerLobby),
        ]);
    }

    public function showLobby(
        #[CurrentUser] User $user,
        Request $request,
        JoinMultiplayerLobbyAction $joinMultiplayerLobby,
    ): JsonResponse {
        $instanceId = $this->sessionInstanceId($request);

        return response()->json($this->lobbyPayload($user, $instanceId, $joinMultiplayerLobby));
    }

    public function storeLobby(
        #[CurrentUser] User $user,
        StoreDiscordActivityLobbyRequest $request,
        CreateDiscordActivityLobbyAction $createDiscordActivityLobby,
    ): JsonResponse {
        $instanceId = $this->sessionInstanceId($request);
        $story = Story::query()
            ->where('slug', $request->string('story')->toString())
            ->firstOrFail();

        if (! StoryPlayability::isPlayable($story)) {
            return response()->json(['message' => 'This story is not available for multiplayer yet.'], 422);
        }

        try {
            $lobby = $createDiscordActivityLobby->handle(
                $user,
                $instanceId,
                $story,
                MultiplayerLobbyModeEnum::from($request->string('mode')->toString()),
            );
        } catch (MultiplayerLobbyJoinException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['lobby_url' => $this->lobbyUrl($lobby)]);
    }

    /**
     * @return array{lobby_url: string|null, message?: string}
     */
    private function lobbyPayload(User $user, string $instanceId, JoinMultiplayerLobbyAction $joinMultiplayerLobby): array
    {
        $lobby = MultiplayerLobby::query()
            ->where('discord_instance_id', $instanceId)
            ->first();

        if ($lobby === null) {
            return ['lobby_url' => null];
        }

        try {
            $lobby = $joinMultiplayerLobby->handle($user, $lobby);
        } catch (MultiplayerLobbyJoinException $exception) {
            return ['lobby_url' => null, 'message' => $exception->getMessage()];
        }

        return ['lobby_url' => $this->lobbyUrl($lobby)];
    }

    private function lobbyUrl(MultiplayerLobby $lobby): string
    {
        return URL::route('multiplayer.lobbies.show', ['lobby' => $lobby->room_code], false);
    }

    private function sessionInstanceId(Request $request): string
    {
        $instanceId = $request->session()->get(self::SESSION_INSTANCE_KEY);

        if (! is_string($instanceId) || $instanceId === '') {
            abort(403, 'Open this activity from a Discord voice channel.');
        }

        return $instanceId;
    }

    /**
     * @return list<array{slug: string, title: string, teaser: string|null}>
     */
    private function playableStories(): array
    {
        return array_values(Story::query()
            ->published()
            ->with('adaptation:id,story_id,adaptation_status')
            ->get(['id', 'slug', 'title', 'teaser', 'status'])
            ->filter(static fn (Story $story): bool => StoryPlayability::isPlayable($story))
            ->sortBy(static fn (Story $story): int => StoryPlayability::orderIndex($story->slug))
            ->map(static fn (Story $story): array => [
                'slug' => $story->slug,
                'title' => $story->title,
                'teaser' => $story->teaser,
            ])
            ->all());
    }
}
