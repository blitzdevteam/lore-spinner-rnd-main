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
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia;

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
        ->assertSee('src="/discord/activity.js?v=8"', false)
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

it('returns the regenerated csrf token so a signed in discord player can open a lobby', function (): void {
    $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });

    $story = discordPlayableStory('dracula', 'Dracula');
    $originalToken = 'activity-page-token';
    fakeDiscord();

    $session = $this->withSession(['_token' => $originalToken])
        ->postJson(route('discord.activity.session'), [
            'code' => 'oauth-code',
            'instance_id' => DISCORD_INSTANCE,
        ], ['X-CSRF-TOKEN' => $originalToken])
        ->assertSuccessful()
        ->assertJsonStructure(['csrf_token']);

    $refreshedToken = $session->json('csrf_token');

    expect($refreshedToken)->toBe(csrf_token())->not->toBe($originalToken);

    $lobby = [
        'story' => $story->slug,
        'mode' => MultiplayerLobbyModeEnum::Arbiter->value,
    ];

    $this->postJson(route('discord.activity.lobby.store'), $lobby, ['X-CSRF-TOKEN' => $originalToken])
        ->assertStatus(419);

    expect(MultiplayerLobby::query()->count())->toBe(0);

    $this->postJson(route('discord.activity.lobby.store'), $lobby, ['X-CSRF-TOKEN' => $refreshedToken])
        ->assertSuccessful();

    expect(MultiplayerLobby::query()->sole()->story_id)->toBe($story->id);
});

it('uses the refreshed csrf token when the activity submits a story after sign in', function (): void {
    $result = Process::path(base_path())->input(<<<'JS'
        import assert from 'node:assert/strict';
        import { readFileSync } from 'node:fs';
        import { runInNewContext } from 'node:vm';

        const listeners = new Map();
        const csrfMeta = { content: 'activity-page-token' };
        const nodes = new Map(['status', 'player', 'picker', 'open-lobby'].map(id => [id, {
            dataset: {},
            addEventListener(event, callback) { listeners.set(event, callback); },
        }]));
        const requests = [];
        const replies = [
            { csrf_token: 'signed-in-token', player: 'Ada', lobby_url: null },
            { lobby_url: null },
            { lobby_url: '/multiplayer/lobbies/ABCDEF' },
        ];
        let destination = null;
        const context = {
            document: {
                body: { dataset: { clientId: 'discord-client', sessionUrl: '/session', lobbyUrl: '/lobby' } },
                getElementById: id => nodes.get(id),
                querySelector: () => csrfMeta,
            },
            window: { location: { search: '', assign: url => { destination = url; } } },
            URLSearchParams,
            FormData: class {
                get(key) { return key === 'story' ? 'dracula' : 'arbiter'; }
            },
            fetch: async (url, options) => {
                requests.push({ url, ...options });
                return { ok: true, json: async () => replies.shift() };
            },
        };

        runInNewContext(readFileSync('public/discord/activity.js', 'utf8'), context);
        await context.request('/session', { method: 'POST' });
        await context.request('/lobby');
        await listeners.get('submit')({ preventDefault() {} });

        assert.equal(requests[0].headers['X-CSRF-TOKEN'], 'activity-page-token');
        assert.equal(requests[1].headers['X-CSRF-TOKEN'], 'signed-in-token');
        assert.equal(requests[2].headers['X-CSRF-TOKEN'], 'signed-in-token');
        assert.equal(csrfMeta.content, 'signed-in-token');
        assert.equal(requests[2].method, 'POST');
        assert.deepEqual(JSON.parse(requests[2].body), { story: 'dracula', mode: 'arbiter' });
        assert.equal(destination, '/multiplayer/lobbies/ABCDEF');
        JS)->run(['node', '--input-type=module']);

    expect($result->successful())->toBeTrue($result->errorOutput());
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
        ->assertHeaderMissing('X-Frame-Options')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('lobby.invite_url', rtrim((string) config('app.url'), '/').'/stories/dracula/multiplayer?room='.$lobby->room_code));
});

it('shares the public runtime reverb settings with the discord lobby', function (): void {
    $this->withoutVite();
    config([
        'broadcasting.connections.reverb.key' => 'runtime-public-key',
        'broadcasting.connections.reverb.secret' => 'private-reverb-secret',
        'broadcasting.connections.reverb.options.host' => 'internal-reverb',
        'reverb.apps.apps.0.options.host' => 'public-reverb.example.com',
        'reverb.apps.apps.0.options.port' => 443,
        'reverb.apps.apps.0.options.scheme' => 'https',
    ]);
    $lobby = discordLobby(discordPlayableStory('dracula', 'Dracula'), discordGuest('100'));

    $this->actingAs($lobby->host, 'user')
        ->withSession([ActivityController::SESSION_INSTANCE_KEY => DISCORD_INSTANCE])
        ->get(route('multiplayer.lobbies.show', ['lobby' => $lobby->room_code]))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Multiplayer/Lobby')
            ->where('reverb.key', 'runtime-public-key')
            ->where('reverb.host', 'public-reverb.example.com')
            ->where('reverb.port', 443)
            ->where('reverb.scheme', 'https')
            ->missing('reverb.secret'))
        ->assertDontSee('private-reverb-secret')
        ->assertDontSee('internal-reverb');
});

it('initializes discord realtime from runtime settings when the build has no reverb key', function (): void {
    $result = Process::path(base_path())->input(<<<'JS'
        import assert from 'node:assert/strict';
        import { readFileSync } from 'node:fs';
        import ts from 'typescript';

        const source = readFileSync('resources/js/utils/reverbEchoOptions.ts', 'utf8');
        const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext } }).outputText;
        const { reverbEchoOptions } = await import('data:text/javascript,' + encodeURIComponent(compiled));
        let cookie = 'other=value; XSRF-TOKEN=first%2Btoken';
        const runtime = { key: 'runtime-key', host: 'reverb.example.com', port: 443, scheme: 'https' };
        const discord = reverbEchoOptions(runtime, {}, '1561830278056509511.discordsays.com', () => cookie);

        assert.equal(discord.key, 'runtime-key');
        assert.equal(discord.wsHost, '1561830278056509511.discordsays.com');
        assert.equal(discord.wsPath, '/reverb');
        assert.equal(discord.wsPort, 443);
        assert.equal(discord.wssPort, 443);
        assert.equal(discord.forceTLS, true);
        assert.equal(discord.channelAuthorization.endpoint, '/broadcasting/auth');
        assert.equal(discord.channelAuthorization.headersProvider()['X-XSRF-TOKEN'], 'first+token');
        cookie = 'XSRF-TOKEN=refreshed%2Ftoken';
        assert.equal(discord.channelAuthorization.headersProvider()['X-XSRF-TOKEN'], 'refreshed/token');

        const website = reverbEchoOptions(null, { key: 'build-key', host: 'localhost', port: '8081', scheme: 'http' }, 'localhost', () => '');
        assert.equal(website.key, 'build-key');
        assert.equal(website.wsHost, 'localhost');
        assert.equal(website.wsPort, 8081);
        assert.equal(website.forceTLS, false);
        assert.equal(website.wsPath, undefined);
        JS)->run(['node', '--input-type=module']);

    expect($result->successful())->toBeTrue($result->errorOutput());
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
