<?php

declare(strict_types=1);

use App\Enums\Story\StoryStatusEnum;
use App\Models\Game;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // The log/null broadcasters skip channel authorization entirely, so the
    // check has to run against a driver that actually consults routes/channels.php.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
        'broadcasting.connections.reverb.options.host' => 'localhost',
        'broadcasting.connections.reverb.options.port' => 8080,
        'broadcasting.connections.reverb.options.scheme' => 'http',
        'broadcasting.connections.reverb.options.useTLS' => false,
    ]);

    // Channel definitions attach to the driver that was default at boot (log in
    // tests). Re-run the file so the driver under test knows them too.
    require base_path('routes/channels.php');

    $this->owner = User::factory()->withCompleteProfile()->create(['is_active' => true]);
    $story = Story::factory()->create([
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);
    $this->game = Game::query()->create([
        'story_id' => $story->id,
        'user_id' => $this->owner->id,
        'current_session_number' => 1,
        'model' => 'claude-sonnet-4-6',
    ]);
});

it('lets the owner subscribe to their private game channel', function (): void {
    $this->actingAs($this->owner, 'user')
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private-game.'.$this->game->id,
            'socket_id' => '1234.5678',
        ])
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('keeps other players off the channel', function (): void {
    $stranger = User::factory()->withCompleteProfile()->create(['is_active' => true]);

    $this->actingAs($stranger, 'user')
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private-game.'.$this->game->id,
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();
});

it('rejects a channel for a game that does not exist', function (): void {
    $this->actingAs($this->owner, 'user')
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private-game.01HDOESNOTEXIST0000000000',
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();
});
