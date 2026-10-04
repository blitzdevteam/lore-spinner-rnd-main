<?php

declare(strict_types=1);

use App\Enums\Story\StoryStatusEnum;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the multiplayer host and player join page', function () {
    $story = Story::factory()->create([
        'status' => StoryStatusEnum::PUBLISHED,
        'title' => 'Treasure Island',
    ]);

    $this->get(route('stories.multiplayer', $story))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Multiplayer/Multiplayer')
            ->where('story.title', 'Treasure Island')
            ->where('story.slug', $story->slug)
            ->where('inviteRoomCode', null));
});

it('passes an invite room code to the multiplayer join page', function () {
    $story = Story::factory()->create([
        'status' => StoryStatusEnum::PUBLISHED,
    ]);

    $this->get(route('stories.multiplayer', ['story' => $story, 'room' => 'abc123']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Multiplayer/Multiplayer')
            ->where('inviteRoomCode', 'ABC123'));
});

it('marks streamer mode as coming soon and unavailable', function () {
    $template = file_get_contents(resource_path('js/pages/Multiplayer/Multiplayer.vue'));

    expect($template)
        ->toContain("name: 'Streamer'")
        ->toContain('available: false')
        ->toContain('Coming Soon')
        ->toContain('!mode.available')
        ->toContain("selectedBackground: '#221b0f'")
        ->toContain('Choose Mode')
        ->toContain('function isModeSelected(mode: StoryMode): boolean')
        ->toContain('function modeCardStyle(mode: StoryMode): Record<string, string>')
        ->toContain('function modeActionStyle(mode: StoryMode): Record<string, string>')
        ->toContain('showModeDetails')
        ->toContain('function openModeDetails(): void')
        ->toContain('function chooseModeFromDetails(mode: StoryMode): void')
        ->toContain('Different ways to play. One unforgettable story.')
        ->toContain('Take turns being the judge and choose the final action after everyone has had a chance to contribute.')
        ->toContain('2-500\\nPlayers');
});

it('offers mode-specific lobby settings before a room is created', function () {
    $template = file_get_contents(resource_path('js/pages/Multiplayer/Multiplayer.vue'));

    expect($template)
        ->toContain('const lobbySettingOptions = computed<LobbySettingOption[]>(() => {')
        ->toContain("if (selectedModeName.value === 'Roulette')")
        ->toContain("{ key: 'choices', label: 'Choices', options: ['On', 'Off'] }")
        ->toContain("{ key: 'customInput' as const, label: 'Custom Input', options: ['On', 'Off'] }")
        ->toContain("{ key: 'voiceOver', label: 'Voice Over', options: ['On', 'Off'] }")
        ->toContain("{ key: 'votingTime', label: 'Countdown', options: ['Off', '3 Min', '4 Min', '5 Min', '6 Min'] }")
        ->toContain('OnOffToggle')
        ->toContain('function isOnOffSetting(setting: LobbySettingOption): boolean')
        ->not->toContain('Power-ups Per User');
});
