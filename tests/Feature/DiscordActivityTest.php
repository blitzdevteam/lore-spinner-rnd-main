<?php

declare(strict_types=1);

use App\Models\DiscordActivityRoom;
use App\Models\DiscordActivityRoomMember;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exempts the test activity endpoints from csrf validation', function () {
    $excludedPaths = app(ValidateCsrfToken::class)->getExcludedPaths();

    expect($excludedPaths)
        ->toContain('discord/activity/begin')
        ->toContain('discord/activity/rooms')
        ->not->toContain('user/games/*/begin');
});

it('serves the test activity when discord launches the site root', function () {
    config(['discord.application_id' => '100']);

    $this->get('/?frame_id=frame&instance_id=instance&platform=desktop')
        ->assertSuccessful()
        ->assertSee('Test only', false)
        ->assertSee('Solo test. You are alone in a small room. A lantern hangs by one shut door.', false)
        ->assertSee('src="/discord/activity.js?v=4"', false)
        ->assertSee('data-url="/discord/activity/begin?test=1"', false)
        ->assertSee('data-room-url="/discord/activity/rooms?test=1"', false)
        ->assertSee('Open this activity from a voice channel to share a room.', false)
        ->assertSee('id="room-players"', false)
        ->assertDontSee('data-page=', false);
});

it('shows the lantern and door opening as a discord activity', function () {
    config(['discord.application_id' => '100']);

    $response = $this->get(route('discord.activity'));

    $response->assertSuccessful()
        ->assertSee('Solo test. You are alone in a small room. A lantern hangs by one shut door.', false)
        ->assertSee('Open the door', false)
        ->assertSee('Take the lantern', false)
        ->assertSee('A light in the dark', false)
        ->assertSee('Current objective', false)
        ->assertSee('Find a way out', false)
        ->assertSee('What will you do?', false)
        ->assertSee('src="/discord/activity.js?v=4"', false)
        ->assertSee('id="room-players"', false)
        ->assertSee('Open this activity from a voice channel to share a room.', false)
        ->assertDontSee('data-room-url=', false)
        ->assertHeader(
            'Content-Security-Policy',
            "frame-ancestors 'self' https://discord.com https://*.discord.com https://*.discordsays.com https://*.discordapp.com",
        );
});

it('points begin at the existing game begin route', function () {
    $gameId = '01m2cy18gknxs7v16k7b1gd6aj';

    $this->get(route('discord.activity', ['game' => $gameId]))
        ->assertSuccessful()
        ->assertSee('/user/games/'.$gameId.'/begin', false);
});

it('opens a test-only begin path without login', function () {
    $this->get(route('discord.activity', ['test' => 1]))
        ->assertSuccessful()
        ->assertSee('Test only', false)
        ->assertSee('Solo test. You are alone in a small room. A lantern hangs by one shut door.', false)
        ->assertSee('data-url="/discord/activity/begin?test=1"', false)
        ->assertSee('data-room-url="/discord/activity/rooms?test=1"', false)
        ->assertDontSee('data-url="/user/games/', false);
});

it('returns the lantern scene from the test begin path without login', function () {
    $this->post(route('discord.activity.begin', ['test' => 1]))
        ->assertSuccessful()
        ->assertJsonPath('scene', 'gate')
        ->assertJsonPath('text', 'Solo test. You are alone in a small room. A lantern hangs by one shut door.')
        ->assertJsonPath('choices.0.label', 'Open the door')
        ->assertJsonPath('choices.1.label', 'Take the lantern');
});

it('refuses the activity begin path without the test flag', function () {
    $this->post(route('discord.activity.begin'))->assertNotFound();
});

it('still requires login for the normal begin route', function () {
    $this->post('/user/games/01m2cy18gknxs7v16k7b1gd6aj/begin')
        ->assertRedirect(route('user.authentication.login.create'));
});

it('refuses the activity room path without the test flag', function () {
    $this->postJson(route('discord.activity.rooms.sync'), roomSnapshot())
        ->assertNotFound();

    expect(DiscordActivityRoom::query()->count())->toBe(0);
});

it('creates one test room for a discord activity instance', function () {
    $response = $this->postJson(route('discord.activity.rooms.sync', ['test' => 1]), roomSnapshot());

    $response->assertSuccessful()
        ->assertJsonPath('test', true)
        ->assertJsonPath('instance_id', '1561830278056509511')
        ->assertJsonPath('participants.0.id', '100')
        ->assertJsonPath('participants.0.username', 'ada')
        ->assertJsonPath('participants.0.global_name', 'Ada')
        ->assertJsonPath('participants.1.username', 'grace')
        ->assertJsonPath('participants.1.global_name', null);

    $roomId = $response->json('room_id');

    $this->assertDatabaseHas(DiscordActivityRoom::class, [
        'id' => $roomId,
        'instance_id' => '1561830278056509511',
        'channel_id' => '1561830278056509512',
        'guild_id' => '1561830278056509513',
    ]);

    $this->assertDatabaseHas(DiscordActivityRoomMember::class, [
        'room_id' => $roomId,
        'discord_user_id' => '100',
        'username' => 'ada',
        'global_name' => 'Ada',
    ]);
});

it('keeps the same room and replaces players for one discord instance', function () {
    $first = $this->postJson(route('discord.activity.rooms.sync', ['test' => 1]), roomSnapshot())
        ->assertSuccessful();

    $second = $this->postJson(route('discord.activity.rooms.sync', ['test' => 1]), roomSnapshot([
        'participants' => [
            ['id' => '200', 'username' => 'grace', 'global_name' => 'Grace'],
        ],
    ]))->assertSuccessful();

    expect($second->json('room_id'))->toBe($first->json('room_id'))
        ->and(DiscordActivityRoom::query()->count())->toBe(1)
        ->and(DiscordActivityRoomMember::query()->count())->toBe(1);

    $this->assertDatabaseHas(DiscordActivityRoomMember::class, [
        'room_id' => $first->json('room_id'),
        'discord_user_id' => '200',
        'username' => 'grace',
        'global_name' => 'Grace',
    ]);

    $this->assertDatabaseMissing(DiscordActivityRoomMember::class, [
        'discord_user_id' => '100',
    ]);
});

it('creates a separate room for a different discord instance', function () {
    $first = $this->postJson(route('discord.activity.rooms.sync', ['test' => 1]), roomSnapshot())
        ->assertSuccessful();

    $second = $this->postJson(route('discord.activity.rooms.sync', ['test' => 1]), roomSnapshot([
        'instance_id' => '1561830278056509599',
        'participants' => [
            ['id' => '300', 'username' => 'linus', 'global_name' => null],
        ],
    ]))->assertSuccessful();

    expect($second->json('room_id'))->not->toBe($first->json('room_id'))
        ->and(DiscordActivityRoom::query()->count())->toBe(2)
        ->and(DiscordActivityRoomMember::query()->where('discord_user_id', '100')->exists())->toBeTrue()
        ->and(DiscordActivityRoomMember::query()->where('discord_user_id', '300')->exists())->toBeTrue();
});

it('rejects a discord instance id that is not numeric', function () {
    $this->postJson(route('discord.activity.rooms.sync', ['test' => 1]), roomSnapshot([
        'instance_id' => 'not-a-snowflake',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors('instance_id');

    expect(DiscordActivityRoom::query()->count())->toBe(0);
});

it('ignores a game id that is not a game key', function () {
    $this->get(route('discord.activity', ['game' => '<script>']))
        ->assertSuccessful()
        ->assertSee('Add ?game= to call /user/games/{game}/begin.', false)
        ->assertDontSee('<script>', false);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function roomSnapshot(array $overrides = []): array
{
    return array_replace([
        'instance_id' => '1561830278056509511',
        'channel_id' => '1561830278056509512',
        'guild_id' => '1561830278056509513',
        'participants' => [
            ['id' => '100', 'username' => 'ada', 'global_name' => 'Ada'],
            ['id' => '200', 'username' => 'grace', 'global_name' => null],
        ],
    ], $overrides);
}
