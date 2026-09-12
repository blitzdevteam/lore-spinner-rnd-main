<?php

declare(strict_types=1);

use App\Enums\Story\StoryStatusEnum;
use App\Models\MultiplayerLobby;
use App\Models\MultiplayerLobbyMember;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

it('uses a thin gray border for non-host player cards in the lobby template', function () {
    $template = file_get_contents(resource_path('js/pages/Multiplayer/Lobby.vue'));

    expect($template)
        ->toContain("member.role === 'host' ? 'border-[#ffbe58]' : 'border-[#303030]'");
});

it('shows only the settings supported by the lobby mode', function () {
    $template = file_get_contents(resource_path('js/pages/Multiplayer/Lobby.vue'));

    expect($template)
        ->toContain("if (liveLobby.value.mode === 'roulette')")
        ->toContain("{ label: 'Custom Input', value: lobbySettings.value.customInput }")
        ->toContain("{ label: 'Voice Over', value: lobbySettings.value.voiceOver }")
        ->toContain("return [{ label: 'Voting Time', value: lobbySettings.value.votingTime }]")
        ->not->toContain('Power-ups Per User');
});
