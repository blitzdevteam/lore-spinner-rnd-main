<?php

declare(strict_types=1);

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyMemberRoleEnum;
use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Http\Controllers\Discord\ActivityController;
use App\Http\Middleware\PrepareDiscordActivityRequest;
use App\Models\MultiplayerLobby;
use App\Models\MultiplayerLobbyMember;
use App\Models\Story;
use App\Models\StoryAdaptation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const DISCORD_INSTANCE = 'i-1561830278056509600-gc-1561837436370292798-1561837436370292799';

beforeEach(function () {
    config([
        'discord.application_id' => '1561830278056509511',
        'discord.client_id' => '',
        'discord.client_secret' => 'secret',
        'discord.bot_token' => 'bot-token',
        'discord.activity_origin' => null,
    ]);
});

it('shows the story picker page with the discord client id', function () {
    $story = discordPlayableStory('dracula', 'Dracula');

    $this->get(route('discord.activity'))
        ->assertSuccessful()
        ->assertSee('data-client-id="1561830278056509511"', false)
        ->assertSee('data-session-url="/discord/activity/session"', false)
        ->assertSee('data-lobby-url="/discord/activity/lobby"', false)
        ->assertSee('value="'.$story->slug.'"', false)
        ->assertSee('Dracula')
        ->assertSee('value="arbiter"', false)
        ->assertSee('value="roulette"', false)
        ->assertSee('src="/discord/activity.js?v=7"', false)
        ->assertDontSee('Test only')
        ->assertHeader(
            'Content-Security-Policy',
            "frame-ancestors 'self' https://discord.com https://*.discord.com https://*.discordsays.com https://*.discordapp.com",
        );
});

it('only lists stories that are playable', function () {
    discordPlayableStory('dracula', 'Dracula');
    Story::factory()->create([
        'slug' => 'unfinished-tale',
        'title' => 'Unfinished Tale',
        'status' => StoryStatusEnum::PUBLISHED,
    ]);

    $this->get(route('discord.activity'))
        ->assertSuccessful()
        ->assertSee('Dracula')
        ->assertDontSee('Unfinished Tale');
});

it('serves the activity when discord launches the site root', function () {
    $this->get('/?frame_id=frame&instance_id='.DISCORD_INSTANCE.'&platform=desktop')
        ->assertSuccessful()
        ->assertSee('data-client-id="1561830278056509511"', false)
        ->assertDontSee('data-page=', false);
});

it('signs a verified discord player in as a guest', function () {
    fakeDiscord();

    $this->postJson(route('discord.activity.session'), [
        'code' => 'oauth-code',
        'instance_id' => DISCORD_INSTANCE,
    ])->assertSuccessful()
        ->assertJsonPath('player', 'Ada')
        ->assertJsonPath('lobby_url', null)
        ->assertJsonMissingPath('access_token')
        ->assertSessionHas(ActivityController::SESSION_INSTANCE_KEY, DISCORD_INSTANCE);

    $user = User::query()->where('discord_user_id', '100')->sole();

    expect($user->first_name)->toBe('Ada')
        ->and($user->email)->toBe('discord-100@'.User::PENDING_EMAIL_DOMAIN)
        ->and($user->is_profile_completed)->toBeFalse();

    $this->assertAuthenticatedAs($user, 'user');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://discord.com/api/v10/oauth2/token'
        && $request['client_id'] === '1561830278056509511'
        && $request['code'] === 'oauth-code');
});

it('reuses the same guest when a discord player comes back', function () {
    $existing = discordGuest('100');
    $existing->update(['first_name' => 'Old name']);
    fakeDiscord();

    $this->postJson(route('discord.activity.session'), [
        'code' => 'oauth-code',
        'instance_id' => DISCORD_INSTANCE,
    ])->assertSuccessful();

    expect(User::query()->where('discord_user_id', '100')->count())->toBe(1)
        ->and($existing->refresh()->first_name)->toBe('Ada');

    $this->assertAuthenticatedAs($existing, 'user');
});

it('refuses a player who is not in the activity instance', function () {
    fakeDiscord(instanceUsers: ['999']);

    $this->postJson(route('discord.activity.session'), [
        'code' => 'oauth-code',
        'instance_id' => DISCORD_INSTANCE,
    ])->assertForbidden();

    expect(User::query()->where('discord_user_id', '100')->exists())->toBeFalse();
    $this->assertGuest('user');
});

it('refuses a discord code that cannot be exchanged', function () {
    Http::fake([
        'discord.com/api/v10/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    $this->postJson(route('discord.activity.session'), [
        'code' => 'bad-code',
        'instance_id' => DISCORD_INSTANCE,
    ])->assertUnauthorized();

    $this->assertGuest('user');
});

it('rejects an instance id with unexpected characters', function () {
    Http::fake();

    $this->postJson(route('discord.activity.session'), [
        'code' => 'oauth-code',
        'instance_id' => '../../etc',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('instance_id');

    Http::assertNothingSent();
});

it('lets the first player open a lobby for the voice channel', function () {
    $story = discordPlayableStory('dracula', 'Dracula');
    $host = discordGuest('100');

    $response = $this->actingAs($host, 'user')
        ->withSession([ActivityController::SESSION_INSTANCE_KEY => DISCORD_INSTANCE])
        ->postJson(route('discord.activity.lobby.store'), [
            'story' => $story->slug,
            'mode' => MultiplayerLobbyModeEnum::Roulette->value,
        ])
        ->assertSuccessful();

    $lobby = MultiplayerLobby::query()->sole();

    $response->assertJsonPath('lobby_url', '/multiplayer/lobbies/'.$lobby->room_code);

    expect($lobby->discord_instance_id)->toBe(DISCORD_INSTANCE)
        ->and($lobby->host_user_id)->toBe($host->id)
        ->and($lobby->story_id)->toBe($story->id)
        ->and($lobby->mode)->toBe(MultiplayerLobbyModeEnum::Roulette)
        ->and($lobby->status)->toBe(MultiplayerLobbyStatusEnum::Waiting)
        ->and($lobby->max_players)->toBe(MultiplayerLobby::MAX_PLAYERS)
        ->and($lobby->settings['decision_window_seconds'])->toBe(MultiplayerLobby::DEFAULT_DECISION_WINDOW_SECONDS);
});

it('joins the existing lobby instead of opening a second one', function () {
    $story = discordPlayableStory('dracula', 'Dracula');
    $lobby = discordLobby($story, discordGuest('100'));
    $second = discordGuest('200');

    $this->actingAs($second, 'user')
        ->withSession([ActivityController::SESSION_INSTANCE_KEY => DISCORD_INSTANCE])
        ->postJson(route('discord.activity.lobby.store'), [
            'story' => $story->slug,
            'mode' => MultiplayerLobbyModeEnum::Arbiter->value,
        ])
        ->assertSuccessful()
        ->assertJsonPath('lobby_url', '/multiplayer/lobbies/'.$lobby->room_code);

    expect(MultiplayerLobby::query()->count())->toBe(1);
    $this->assertDatabaseHas(MultiplayerLobbyMember::class, [
        'lobby_id' => $lobby->id,
        'user_id' => $second->id,
        'role' => MultiplayerLobbyMemberRoleEnum::Player->value,
    ]);
});

it('sends a signed in player straight to the voice channel lobby', function () {
    $story = discordPlayableStory('dracula', 'Dracula');
    $lobby = discordLobby($story, discordGuest('200'));
    fakeDiscord();

    $this->postJson(route('discord.activity.session'), [
        'code' => 'oauth-code',
        'instance_id' => DISCORD_INSTANCE,
    ])->assertSuccessful()
        ->assertJsonPath('lobby_url', '/multiplayer/lobbies/'.$lobby->room_code);

    $guest = User::query()->where('discord_user_id', '100')->sole();

    expect($lobby->isMember($guest))->toBeTrue();
});

it('joins the lobby while waiting for the host to pick a story', function () {
    $player = discordGuest('200');

    $this->actingAs($player, 'user')
        ->withSession([ActivityController::SESSION_INSTANCE_KEY => DISCORD_INSTANCE])
        ->getJson(route('discord.activity.lobby.show'))
        ->assertSuccessful()
        ->assertJsonPath('lobby_url', null);

    $lobby = discordLobby(discordPlayableStory('dracula', 'Dracula'), discordGuest('100'));

    $this->actingAs($player, 'user')
        ->withSession([ActivityController::SESSION_INSTANCE_KEY => DISCORD_INSTANCE])
        ->getJson(route('discord.activity.lobby.show'))
        ->assertSuccessful()
        ->assertJsonPath('lobby_url', '/multiplayer/lobbies/'.$lobby->room_code);

    expect($lobby->isMember($player))->toBeTrue();
});

it('tells a late player when the voice channel story already started', function () {
    $lobby = discordLobby(discordPlayableStory('dracula', 'Dracula'), discordGuest('100'));
    $lobby->update(['status' => MultiplayerLobbyStatusEnum::Started]);

    $this->actingAs(discordGuest('300'), 'user')
        ->withSession([ActivityController::SESSION_INSTANCE_KEY => DISCORD_INSTANCE])
        ->getJson(route('discord.activity.lobby.show'))
        ->assertSuccessful()
        ->assertJsonPath('lobby_url', null)
        ->assertJsonStructure(['message']);
});

it('refuses a story that is not playable', function () {
    $story = Story::factory()->create(['status' => StoryStatusEnum::PUBLISHED]);

    $this->actingAs(discordGuest('100'), 'user')
        ->withSession([ActivityController::SESSION_INSTANCE_KEY => DISCORD_INSTANCE])
        ->postJson(route('discord.activity.lobby.store'), [
            'story' => $story->slug,
            'mode' => MultiplayerLobbyModeEnum::Arbiter->value,
        ])
        ->assertUnprocessable();

    expect(MultiplayerLobby::query()->count())->toBe(0);
});

it('refuses lobby routes without a verified discord session', function () {
    $story = discordPlayableStory('dracula', 'Dracula');
    $user = discordGuest('100');

    $this->actingAs($user, 'user')
        ->getJson(route('discord.activity.lobby.show'))
        ->assertForbidden();

    $this->actingAs($user, 'user')
        ->postJson(route('discord.activity.lobby.store'), [
            'story' => $story->slug,
            'mode' => MultiplayerLobbyModeEnum::Arbiter->value,
        ])
        ->assertForbidden();

    expect(MultiplayerLobby::query()->count())->toBe(0);
});

it('refuses lobby routes for visitors who are not signed in', function () {
    $this->getJson(route('discord.activity.lobby.show'))->assertUnauthorized();
});

it('points urls at the discordsays origin for discord players', function () {
    $lobby = discordLobby(discordPlayableStory('dracula', 'Dracula'), discordGuest('100'));
    $player = discordGuest('200');
    $lobby->members()->create([
        'user_id' => $player->id,
        'role' => MultiplayerLobbyMemberRoleEnum::Player,
        'joined_at' => now(),
        'last_seen_at' => now(),
    ]);

    $this->actingAs($player, 'user')
        ->withSession([ActivityController::SESSION_INSTANCE_KEY => DISCORD_INSTANCE])
        ->patch(route('multiplayer.lobbies.readiness.update', ['lobby' => $lobby->room_code]), ['is_ready' => true])
        ->assertRedirect('https://1561830278056509511.discordsays.com/multiplayer/lobbies/'.$lobby->room_code);
});

it('keeps normal website redirects on the app host', function () {
    $lobby = discordLobby(discordPlayableStory('dracula', 'Dracula'), discordGuest('100'));
    $player = discordGuest('200');
    $lobby->members()->create([
        'user_id' => $player->id,
        'role' => MultiplayerLobbyMemberRoleEnum::Player,
        'joined_at' => now(),
        'last_seen_at' => now(),
    ]);

    $this->actingAs($player, 'user')
        ->patch(route('multiplayer.lobbies.readiness.update', ['lobby' => $lobby->room_code]), ['is_ready' => true])
        ->assertRedirect(route('multiplayer.lobbies.show', ['lobby' => $lobby->room_code]));
});

it('gives discord activity requests a cross-site host-only session cookie', function () {
    config([
        'session.same_site' => 'lax',
        'session.secure' => false,
        'session.partitioned' => false,
        'session.domain' => '.lorespinner.test',
    ]);

    $cookie = collect($this->withHeader('Referer', 'https://1561830278056509511.discordsays.com/')
        ->get(route('discord.activity'))
        ->assertSuccessful()
        ->headers->getCookies())
        ->first(fn ($cookie): bool => $cookie->getName() === config('session.cookie'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->getSameSite())->toBe('none')
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isPartitioned())->toBeTrue()
        ->and($cookie->getDomain())->toBeNull();
});

it('lets discord frame platform pages opened inside the activity', function () {
    $this->withoutVite();
    $lobby = discordLobby(discordPlayableStory('dracula', 'Dracula'), discordGuest('100'));

    $this->actingAs($lobby->host, 'user')
        ->withHeader('Referer', 'https://1561830278056509511.discordsays.com/discord/activity')
        ->get(route('multiplayer.lobbies.show', ['lobby' => $lobby->room_code]))
        ->assertSuccessful()
        ->assertHeader('Content-Security-Policy', PrepareDiscordActivityRequest::FRAME_ANCESTORS)
        ->assertHeaderMissing('X-Frame-Options');
});

it('does not add the discord frame policy to website pages', function () {
    $this->get('/up')->assertHeaderMissing('Content-Security-Policy');
});

it('keeps the website session cookie settings outside discord', function () {
    config([
        'session.same_site' => 'lax',
        'session.secure' => false,
        'session.partitioned' => false,
        'session.domain' => '.lorespinner.test',
    ]);

    $cookie = collect($this->get(route('discord.activity'))
        ->assertSuccessful()
        ->headers->getCookies())
        ->first(fn ($cookie): bool => $cookie->getName() === config('session.cookie'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->isSecure())->toBeFalse()
        ->and($cookie->isPartitioned())->toBeFalse()
        ->and($cookie->getDomain())->toBe('.lorespinner.test');
});

/**
 * @param  list<string>  $instanceUsers
 */
function fakeDiscord(array $instanceUsers = ['100', '200']): void
{
    Http::fake([
        'discord.com/api/v10/oauth2/token' => Http::response(['access_token' => 'user-token', 'token_type' => 'Bearer']),
        'discord.com/api/v10/users/@me' => Http::response(['id' => '100', 'username' => 'ada', 'global_name' => 'Ada']),
        'discord.com/api/v10/applications/1561830278056509511/activity-instances/*' => Http::response([
            'application_id' => '1561830278056509511',
            'instance_id' => DISCORD_INSTANCE,
            'users' => $instanceUsers,
        ]),
    ]);
}

function discordPlayableStory(string $slug, string $title): Story
{
    config(['stories.launch_playable_slugs' => [$slug], 'stories.boiled_playable_slugs' => []]);

    $story = Story::factory()->create([
        'slug' => $slug,
        'title' => $title,
        'status' => StoryStatusEnum::PUBLISHED,
    ]);

    StoryAdaptation::query()->create([
        'story_id' => $story->id,
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
        'boiler_enabled' => true,
    ]);

    return $story;
}

function discordGuest(string $discordUserId): User
{
    return User::factory()->create([
        'discord_user_id' => $discordUserId,
        'username' => null,
        'email' => 'discord-'.$discordUserId.'@'.User::PENDING_EMAIL_DOMAIN,
    ]);
}

function discordLobby(Story $story, User $host): MultiplayerLobby
{
    $lobby = MultiplayerLobby::factory()->create([
        'story_id' => $story->id,
        'host_user_id' => $host->id,
        'discord_instance_id' => DISCORD_INSTANCE,
        'status' => MultiplayerLobbyStatusEnum::Waiting,
        'max_players' => MultiplayerLobby::MAX_PLAYERS,
    ]);

    MultiplayerLobbyMember::factory()->host()->create([
        'lobby_id' => $lobby->id,
        'user_id' => $host->id,
    ]);

    return $lobby;
}
