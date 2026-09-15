<?php

declare(strict_types=1);

use App\Actions\Multiplayer\ChooseMultiplayerLobbyRouletteChoiceAction;
use App\Actions\Multiplayer\CreateMultiplayerLobbyAction;
use App\Actions\Multiplayer\ResolveMultiplayerLobbyDecisionDeadlineAction;
use App\Actions\Multiplayer\SubmitMultiplayerLobbyProposalAction;
use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Events\MultiplayerArbiterStateChanged;
use App\Events\MultiplayerLobbyCreated;
use App\Exceptions\Multiplayer\MultiplayerLobbyStateException;
use App\Jobs\Multiplayer\ResolveMultiplayerLobbyDecisionDeadlineJob;
use App\Models\Game;
use App\Models\MultiplayerLobby;
use App\Models\MultiplayerLobbyMember;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('normalizes settings to the capabilities of each multiplayer mode', function (): void {
    Event::fake([MultiplayerLobbyCreated::class]);

    $host = User::factory()->create();
    $story = Story::factory()->create();
    $createLobby = app(CreateMultiplayerLobbyAction::class);

    $arbiterLobby = $createLobby->handle(
        $host,
        $story,
        MultiplayerLobbyModeEnum::Arbiter,
        3,
        [
            'custom_input_enabled' => true,
            'voice_over_enabled' => true,
            'decision_window_seconds' => 30,
        ],
    );
    $rouletteLobby = $createLobby->handle(
        $host,
        $story,
        MultiplayerLobbyModeEnum::Roulette,
        3,
        [
            'custom_input_enabled' => true,
            'voice_over_enabled' => true,
            'decision_window_seconds' => 10,
        ],
    );

    expect($arbiterLobby->decisionWindowSeconds())->toBe(30)
        ->and($arbiterLobby->customInputEnabled())->toBeFalse()
        ->and($arbiterLobby->voiceOverEnabled())->toBeFalse()
        ->and($rouletteLobby->decisionWindowSeconds())->toBe(10)
        ->and($rouletteLobby->customInputEnabled())->toBeTrue()
        ->and($rouletteLobby->voiceOverEnabled())->toBeTrue();
});

it('accepts only voting time for arbiter lobbies and requires roulette-only settings for roulette lobbies', function (): void {
    $host = User::factory()->withCompleteProfile()->markEmailAsVerified()->create([
        'is_active' => true,
    ]);
    $story = Story::factory()->create([
        'slug' => 'the-wonderful-wizard-of-oz',
        'status' => StoryStatusEnum::PUBLISHED,
    ]);

    $this->actingAs($host)
        ->post(route('stories.multiplayer.lobbies.store', $story), [
            'mode' => 'arbiter',
            'max_players' => 3,
            'settings' => [
                'decision_window_seconds' => 30,
            ],
        ])
        ->assertRedirect();

    $arbiterLobby = MultiplayerLobby::query()->sole();

    expect($arbiterLobby->decisionWindowSeconds())->toBe(30)
        ->and($arbiterLobby->customInputEnabled())->toBeFalse()
        ->and($arbiterLobby->voiceOverEnabled())->toBeFalse();

    $this->actingAs($host)
        ->post(route('stories.multiplayer.lobbies.store', $story), [
            'mode' => 'roulette',
            'max_players' => 3,
            'settings' => [
                'decision_window_seconds' => 10,
            ],
        ])
        ->assertSessionHasErrors([
            'settings.custom_input_enabled',
            'settings.voice_over_enabled',
        ]);
});

it('starts the arbiter decision window with the first submitted proposal', function (): void {
    Event::fake([MultiplayerArbiterStateChanged::class]);
    Queue::fake();
    Carbon::setTestNow('2026-09-06 12:00:00');

    try {
        $host = User::factory()->create();
        $firstContributor = User::factory()->create();
        $secondContributor = User::factory()->create();
        $story = Story::factory()->create();
        $game = Game::query()->create([
            'story_id' => $story->id,
            'user_id' => $host->id,
            'current_session_number' => 1,
            'model' => 'claude-sonnet-4-6',
        ]);
        $lobby = MultiplayerLobby::factory()->create([
            'story_id' => $story->id,
            'host_user_id' => $host->id,
            'game_id' => $game->id,
            'mode' => MultiplayerLobbyModeEnum::Arbiter,
            'status' => MultiplayerLobbyStatusEnum::Started,
            'turn_status' => MultiplayerArbiterTurnStatusEnum::CollectingProposals,
            'settings' => [
                'custom_input_enabled' => false,
                'voice_over_enabled' => false,
                'power_ups_per_user' => 10,
                'decision_window_seconds' => 180,
            ],
        ]);
        $arbiter = MultiplayerLobbyMember::factory()->host()->create([
            'lobby_id' => $lobby->id,
            'user_id' => $host->id,
        ]);
        MultiplayerLobbyMember::factory()->create([
            'lobby_id' => $lobby->id,
            'user_id' => $firstContributor->id,
        ]);
        MultiplayerLobbyMember::factory()->create([
            'lobby_id' => $lobby->id,
            'user_id' => $secondContributor->id,
        ]);
        $sourcePrompt = $game->prompts()->create([
            'session_number' => 1,
            'response' => 'A crossroads opens before the party.',
            'choices' => [],
        ]);
        $lobby->update([
            'current_arbiter_member_id' => $arbiter->id,
            'current_source_prompt_id' => $sourcePrompt->id,
        ]);

        app(SubmitMultiplayerLobbyProposalAction::class)->handle($firstContributor, $lobby, 'Take the path through the woods.');

        $lobby->refresh();

        expect($lobby->turn_status)->toBe(MultiplayerArbiterTurnStatusEnum::CollectingProposals)
            ->and($lobby->decision_deadline_at?->toISOString())
            ->toBe(now()->addSeconds(180)->toISOString());

        app(SubmitMultiplayerLobbyProposalAction::class)->handle($secondContributor, $lobby, 'Wait for the storm to pass.');

        $lobby->refresh();

        expect($lobby->turn_status)->toBe(MultiplayerArbiterTurnStatusEnum::AwaitingArbiter)
            ->and($lobby->decision_deadline_at?->toISOString())
            ->toBe(now()->addSeconds(180)->toISOString());

        Queue::assertPushed(ResolveMultiplayerLobbyDecisionDeadlineJob::class, 1);
    } finally {
        Carbon::setTestNow();
    }
});

it('resolves the submitted arbiter proposal when the first decision window expires', function (): void {
    Event::fake([MultiplayerArbiterStateChanged::class]);
    Queue::fake();
    Carbon::setTestNow('2026-09-06 12:00:00');

    try {
        $host = User::factory()->create();
        $contributor = User::factory()->create();
        $story = Story::factory()->create();
        $game = Game::query()->create([
            'story_id' => $story->id,
            'user_id' => $host->id,
            'current_session_number' => 1,
            'model' => 'claude-sonnet-4-6',
        ]);
        $lobby = MultiplayerLobby::factory()->create([
            'story_id' => $story->id,
            'host_user_id' => $host->id,
            'game_id' => $game->id,
            'mode' => MultiplayerLobbyModeEnum::Arbiter,
            'status' => MultiplayerLobbyStatusEnum::Started,
            'turn_status' => MultiplayerArbiterTurnStatusEnum::CollectingProposals,
            'settings' => [
                'custom_input_enabled' => false,
                'voice_over_enabled' => false,
                'power_ups_per_user' => 10,
                'decision_window_seconds' => 180,
            ],
        ]);
        $arbiter = MultiplayerLobbyMember::factory()->host()->create([
            'lobby_id' => $lobby->id,
            'user_id' => $host->id,
        ]);
        $contributorMember = MultiplayerLobbyMember::factory()->create([
            'lobby_id' => $lobby->id,
            'user_id' => $contributor->id,
        ]);
        $sourcePrompt = $game->prompts()->create([
            'session_number' => 1,
            'response' => 'A crossroads opens before the party.',
            'choices' => [],
        ]);
        $lobby->update([
            'current_arbiter_member_id' => $arbiter->id,
            'current_source_prompt_id' => $sourcePrompt->id,
        ]);

        app(SubmitMultiplayerLobbyProposalAction::class)->handle($contributor, $lobby, 'Take the path through the woods.');
        $lobby->refresh();
        $deadlineTimestamp = $lobby->decision_deadline_at?->getTimestamp();

        Carbon::setTestNow('2026-09-06 12:03:01');

        $selection = app(ResolveMultiplayerLobbyDecisionDeadlineAction::class)->handle(
            $lobby->id,
            (string) $sourcePrompt->id,
            $deadlineTimestamp,
        );

        expect($selection)->not->toBeNull()
            ->and($selection['proposal_id'])->toBe(
                (string) $lobby->proposals()->where('member_id', $contributorMember->id)->value('id'),
            )
            ->and($selection['lobby']->turn_status)->toBe(MultiplayerArbiterTurnStatusEnum::Narrating)
            ->and($selection['lobby']->decision_deadline_at)->toBeNull();
    } finally {
        Carbon::setTestNow();
    }
});

it('rejects roulette custom input when the host has disabled it while allowing generated choices', function (): void {
    Event::fake([MultiplayerArbiterStateChanged::class]);

    $host = User::factory()->create();
    $chooser = User::factory()->create();
    $story = Story::factory()->create();
    $game = Game::query()->create([
        'story_id' => $story->id,
        'user_id' => $host->id,
        'current_session_number' => 1,
        'model' => 'claude-sonnet-4-6',
    ]);
    $lobby = MultiplayerLobby::factory()->roulette()->create([
        'story_id' => $story->id,
        'host_user_id' => $host->id,
        'game_id' => $game->id,
        'status' => MultiplayerLobbyStatusEnum::Started,
        'turn_status' => MultiplayerArbiterTurnStatusEnum::AwaitingRouletteChoice,
        'settings' => [
            'custom_input_enabled' => false,
            'voice_over_enabled' => false,
            'power_ups_per_user' => 10,
            'decision_window_seconds' => 10,
        ],
    ]);
    $chooserMember = MultiplayerLobbyMember::factory()->create([
        'lobby_id' => $lobby->id,
        'user_id' => $chooser->id,
    ]);
    $sourcePrompt = $game->prompts()->create([
        'session_number' => 1,
        'response' => 'The road splits beneath the storm clouds.',
        'choices' => [
            ['text' => 'Follow the yellow road.'],
            ['text' => 'Shelter in the nearby cottage.'],
        ],
    ]);
    $lobby->update([
        'current_arbiter_member_id' => $chooserMember->id,
        'current_source_prompt_id' => $sourcePrompt->id,
    ]);
    $chooseRouletteChoice = app(ChooseMultiplayerLobbyRouletteChoiceAction::class);

    expect(fn (): array => $chooseRouletteChoice->handle($chooser, $lobby, null, 'Invent a fourth path.'))
        ->toThrow(MultiplayerLobbyStateException::class, 'Custom input is disabled for this Roulette lobby.');

    $selection = $chooseRouletteChoice->handle($chooser, $lobby, 0);

    expect($selection['lobby']->turn_status)->toBe(MultiplayerArbiterTurnStatusEnum::Narrating)
        ->and($selection['lobby']->proposals()->sole()->choice_index)->toBe(0);
});

it('does not expose narration audio for a roulette lobby with voice over disabled', function (): void {
    $host = User::factory()->create();
    $story = Story::factory()->create();
    $game = Game::query()->create([
        'story_id' => $story->id,
        'user_id' => $host->id,
        'current_session_number' => 1,
        'model' => 'claude-sonnet-4-6',
    ]);
    $prompt = $game->prompts()->create([
        'session_number' => 1,
        'response' => 'The storm is drawing closer.',
        'choices' => [],
    ]);
    $lobby = MultiplayerLobby::factory()->roulette()->create([
        'story_id' => $story->id,
        'host_user_id' => $host->id,
        'game_id' => $game->id,
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
        ->get(route('user.games.tts', [$game, $prompt]))
        ->assertForbidden();
});
