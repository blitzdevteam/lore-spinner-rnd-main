<?php

declare(strict_types=1);

use App\Actions\Multiplayer\StartMultiplayerLobbyAction;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Events\MultiplayerLobbyStarted;
use App\Models\MultiplayerLobby;
use App\Models\MultiplayerLobbyMember;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the waiting lobby sidebar props for the host', function () {
    $this->withoutVite();

    $host = User::factory()->withCompleteProfile()->markEmailAsVerified()->create([
        'first_name' => 'Alex',
        'last_name' => 'Stone',
        'is_active' => true,
    ]);
    $story = Story::factory()->create([
        'status' => StoryStatusEnum::PUBLISHED,
    ]);
    $lobby = MultiplayerLobby::factory()->create([
        'story_id' => $story->id,
        'host_user_id' => $host->id,
        'room_code' => 'TRS-7X2Q',
        'max_players' => 4,
        'settings' => [
            'custom_input_enabled' => true,
            'voice_over_enabled' => false,
            'power_ups_per_user' => 10,
            'decision_window_seconds' => 20,
        ],
    ]);

    MultiplayerLobbyMember::factory()->host()->create([
        'lobby_id' => $lobby->id,
        'user_id' => $host->id,
        'joined_at' => now()->subMinute(),
    ]);

    $guest = User::factory()->withCompleteProfile()->markEmailAsVerified()->create([
        'is_active' => true,
    ]);

    MultiplayerLobbyMember::factory()->create([
        'lobby_id' => $lobby->id,
        'user_id' => $guest->id,
        'joined_at' => now(),
    ]);

    $this->actingAs($host)
        ->get(route('multiplayer.lobbies.show', $lobby))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Multiplayer/Lobby')
            ->where('lobby.room_code', 'TRS-7X2Q')
            ->where('lobby.invite_url', route('stories.multiplayer', [
                'story' => $story,
                'room' => 'TRS-7X2Q',
            ]))
            ->where('lobby.mode', 'arbiter')
            ->where('lobby.max_players', 4)
            ->where('lobby.is_host', true)
            ->where('lobby.host.full_name', 'Alex Stone')
            ->where('lobby.settings.custom_input_enabled', true)
            ->where('lobby.settings.decision_window_seconds', 20)
            ->where('lobby.settings.power_ups_per_user', 10)
            ->has('lobby.members', 2)
            ->where('lobby.members.0.role', 'host')
            ->where('lobby.members.1.role', 'player'));
});

it('renders the invite card with a room link and join code', function () {
    $card = file_get_contents(resource_path('js/components/MultiplayerInviteCard.vue'));
    $lobby = file_get_contents(resource_path('js/pages/Multiplayer/Lobby.vue'));
    $arbiter = file_get_contents(resource_path('js/pages/Arbiter.vue'));

    expect($card)
        ->toContain('Invite Players')
        ->toContain('Room Link')
        ->toContain('Join Code')
        ->toContain('displayInviteUrl')
        ->toContain("copyValue('link', inviteUrl)")
        ->toContain("copyValue('code', roomCode)")
        ->toContain('star-four.svg')
        ->toContain('copy.svg')
        ->toContain('size-5')
        ->toContain('size-[29px]');

    expect($lobby)
        ->toContain('MultiplayerInviteCard')
        ->toContain(':invite-url="inviteLink"')
        ->toContain(':room-code="roomCode"');

    expect($arbiter)
        ->toContain('MultiplayerInviteCard')
        ->toContain(':invite-url="inviteLink"')
        ->toContain(':room-code="roomCode"');
});

it('uses a thin gray border for non-host player cards in the lobby template', function () {
    $template = file_get_contents(resource_path('js/pages/Multiplayer/Lobby.vue'));

    expect($template)
        ->toContain("member.role === 'host' ? 'border-[#ffbe58]' : 'border-[#303030]'");
});

it('shows only the settings supported by the lobby mode', function () {
    $template = file_get_contents(resource_path('js/pages/Multiplayer/Lobby.vue'));

    expect($template)
        ->toContain("if (liveLobby.value.mode === 'roulette')")
        ->toContain("{ key: 'choices', label: 'Choices', options: ['On', 'Off'] }")
        ->toContain("{ key: 'customInput' as const, label: 'Custom Input', options: ['On', 'Off'] }")
        ->toContain("{ key: 'voiceOver', label: 'Voice Over', options: ['On', 'Off'] }")
        ->toContain("{ key: 'votingTime', label: 'Countdown', options: ['Off', '3 Min', '4 Min', '5 Min', '6 Min'] }")
        ->toContain('OnOffToggle')
        ->toContain('function isOnOffSetting(setting: LobbySettingOption): boolean')
        ->not->toContain('Power-ups Per User');
});

it('keeps four lobby seats visible and only adds a card when another player joins', function () {
    $lobby = file_get_contents(resource_path('js/pages/Multiplayer/Lobby.vue'));
    $setup = file_get_contents(resource_path('js/pages/Multiplayer/Multiplayer.vue'));

    expect($lobby)
        ->toContain('const visibleLobbySeatFloor = 4;')
        ->toContain('Math.min(liveLobby.value.max_players, Math.max(members.value.length, visibleLobbySeatFloor))');

    expect($setup)
        ->toContain('max_players: 10')
        ->toContain('lobbyCreationForm.max_players = 10')
        ->toContain('3-10\nPlayers');
});

it('lets a room hold ten players and still start when three are ready', function () {
    Event::fake([MultiplayerLobbyStarted::class]);

    $host = User::factory()->withCompleteProfile()->markEmailAsVerified()->create([
        'is_active' => true,
    ]);
    $story = Story::factory()->create([
        'slug' => 'the-wonderful-wizard-of-oz',
        'status' => StoryStatusEnum::PUBLISHED,
    ]);
    $story->adaptation()->create([
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
    ]);

    $this->actingAs($host)
        ->post(route('stories.multiplayer.lobbies.store', $story), [
            'mode' => 'arbiter',
            'max_players' => MultiplayerLobby::MAX_PLAYERS,
            'settings' => [
                'choices_enabled' => true,
                'voice_over_enabled' => false,
                'decision_window_seconds' => 180,
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $lobby = MultiplayerLobby::query()->where('host_user_id', $host->id)->sole();

    expect($lobby->max_players)->toBe(10);

    $this->actingAs($host)
        ->post(route('stories.multiplayer.lobbies.store', $story), [
            'mode' => 'roulette',
            'max_players' => 11,
            'settings' => [
                'choices_enabled' => true,
                'custom_input_enabled' => false,
                'voice_over_enabled' => false,
                'decision_window_seconds' => 180,
            ],
        ])
        ->assertSessionHasErrors([
            'max_players' => 'A multiplayer room can have between 3 and 10 players.',
        ]);

    $players = User::factory()->count(2)->withCompleteProfile()->markEmailAsVerified()->create([
        'is_active' => true,
    ]);

    foreach ($players as $player) {
        MultiplayerLobbyMember::factory()->create([
            'lobby_id' => $lobby->id,
            'user_id' => $player->id,
            'is_ready' => true,
        ]);
    }

    $lobby->members()->where('user_id', $host->id)->update(['is_ready' => true]);

    app(StartMultiplayerLobbyAction::class)->handle($host, $lobby);

    expect($lobby->refresh()->status)->toBe(MultiplayerLobbyStatusEnum::Started)
        ->and($lobby->members()->count())->toBe(3);
});

it('turns away an eleventh player when the lobby is full', function () {
    $host = User::factory()->withCompleteProfile()->markEmailAsVerified()->create([
        'is_active' => true,
    ]);
    $story = Story::factory()->create([
        'status' => StoryStatusEnum::PUBLISHED,
    ]);
    $lobby = MultiplayerLobby::factory()->create([
        'story_id' => $story->id,
        'host_user_id' => $host->id,
        'room_code' => 'TRS-FULL10',
        'max_players' => MultiplayerLobby::MAX_PLAYERS,
    ]);

    MultiplayerLobbyMember::factory()->host()->create([
        'lobby_id' => $lobby->id,
        'user_id' => $host->id,
    ]);

    User::factory()
        ->count(9)
        ->withCompleteProfile()
        ->markEmailAsVerified()
        ->create(['is_active' => true])
        ->each(function (User $player) use ($lobby): void {
            MultiplayerLobbyMember::factory()->create([
                'lobby_id' => $lobby->id,
                'user_id' => $player->id,
            ]);
        });

    $latePlayer = User::factory()->withCompleteProfile()->markEmailAsVerified()->create([
        'is_active' => true,
    ]);

    $this->actingAs($latePlayer)
        ->post(route('multiplayer.lobbies.join'), [
            'room_code' => 'TRS-FULL10',
        ])
        ->assertSessionHasErrors([
            'room_code' => 'This lobby is already full.',
        ]);

    expect($lobby->members()->count())->toBe(10);
});
