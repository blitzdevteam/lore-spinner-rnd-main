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
    ]);

    $this->actingAs($host)
        ->get(route('multiplayer.lobbies.show', $lobby))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Multiplayer/Lobby')
            ->where('lobby.room_code', 'TRS-7X2Q')
            ->where('lobby.mode', 'arbiter')
            ->where('lobby.max_players', 4)
            ->where('lobby.is_host', true)
            ->where('lobby.host.full_name', 'Alex Stone')
            ->where('lobby.settings.custom_input_enabled', true)
            ->where('lobby.settings.decision_window_seconds', 20)
            ->where('lobby.settings.power_ups_per_user', 10)
            ->has('lobby.members', 1));
});
