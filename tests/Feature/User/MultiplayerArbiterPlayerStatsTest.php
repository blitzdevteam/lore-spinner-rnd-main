<?php

declare(strict_types=1);

use App\Actions\Multiplayer\ResolveMultiplayerLobbyDecisionDeadlineAction;
use App\Actions\Multiplayer\SelectMultiplayerLobbyProposalAction;
use App\Actions\Multiplayer\SubmitMultiplayerLobbyProposalAction;
use App\Enums\Multiplayer\MultiplayerArbiterTurnStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyModeEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Events\MultiplayerArbiterStateChanged;
use App\Models\Game;
use App\Models\MultiplayerLobby;
use App\Models\MultiplayerLobbyMember;
use App\Models\MultiplayerLobbyProposal;
use App\Models\Prompt;
use App\Models\Story;
use App\Models\User;
use App\Support\Multiplayer\MultiplayerLobbyBroadcastPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     host: User,
 *     firstContributor: User,
 *     secondContributor: User,
 *     lobby: MultiplayerLobby,
 *     arbiter: MultiplayerLobbyMember,
 *     firstMember: MultiplayerLobbyMember,
 *     secondMember: MultiplayerLobbyMember,
 *     sourcePrompt: Prompt
 * }
 */
function createStartedArbiterLobby(): array
{
    $host = User::factory()->withCompleteProfile()->markEmailAsVerified()->create([
        'is_active' => true,
    ]);
    $firstContributor = User::factory()->withCompleteProfile()->markEmailAsVerified()->create([
        'is_active' => true,
    ]);
    $secondContributor = User::factory()->withCompleteProfile()->markEmailAsVerified()->create([
        'is_active' => true,
    ]);
    $story = Story::factory()->create([
        'status' => StoryStatusEnum::PUBLISHED,
    ]);
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
        'joined_at' => now()->subMinutes(2),
    ]);
    $firstMember = MultiplayerLobbyMember::factory()->create([
        'lobby_id' => $lobby->id,
        'user_id' => $firstContributor->id,
        'joined_at' => now()->subMinute(),
    ]);
    $secondMember = MultiplayerLobbyMember::factory()->create([
        'lobby_id' => $lobby->id,
        'user_id' => $secondContributor->id,
        'joined_at' => now(),
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

    return [
        'host' => $host,
        'firstContributor' => $firstContributor,
        'secondContributor' => $secondContributor,
        'lobby' => $lobby->refresh(),
        'arbiter' => $arbiter,
        'firstMember' => $firstMember,
        'secondMember' => $secondMember,
        'sourcePrompt' => $sourcePrompt,
    ];
}

it('counts submitted proposals as turns played without awarding score yet', function (): void {
    Event::fake([MultiplayerArbiterStateChanged::class]);
    Queue::fake();

    [
        'host' => $host,
        'firstContributor' => $firstContributor,
        'secondContributor' => $secondContributor,
        'lobby' => $lobby,
        'firstMember' => $firstMember,
        'secondMember' => $secondMember,
    ] = createStartedArbiterLobby();

    $this->withoutVite();

    app(SubmitMultiplayerLobbyProposalAction::class)->handle($firstContributor, $lobby, 'Take the path through the woods.');
    app(SubmitMultiplayerLobbyProposalAction::class)->handle($secondContributor, $lobby, 'Wait for the storm to pass.');

    $this->actingAs($host)
        ->get(route('multiplayer.lobbies.show', $lobby))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Arbiter')
            ->where('lobby.members.0.score', 0)
            ->where('lobby.members.0.turns_played', 0)
            ->where('lobby.members.1.id', $firstMember->id)
            ->where('lobby.members.1.score', 0)
            ->where('lobby.members.1.turns_played', 1)
            ->where('lobby.members.2.id', $secondMember->id)
            ->where('lobby.members.2.score', 0)
            ->where('lobby.members.2.turns_played', 1));
});

it('awards one point to the player whose proposal the arbiter selects', function (): void {
    Event::fake([MultiplayerArbiterStateChanged::class]);
    Queue::fake();

    [
        'host' => $host,
        'firstContributor' => $firstContributor,
        'secondContributor' => $secondContributor,
        'lobby' => $lobby,
        'firstMember' => $firstMember,
        'secondMember' => $secondMember,
    ] = createStartedArbiterLobby();

    $this->withoutVite();

    app(SubmitMultiplayerLobbyProposalAction::class)->handle($firstContributor, $lobby, 'Take the path through the woods.');
    app(SubmitMultiplayerLobbyProposalAction::class)->handle($secondContributor, $lobby, 'Wait for the storm to pass.');

    $selectedProposal = MultiplayerLobbyProposal::query()
        ->where('member_id', $firstMember->id)
        ->sole();

    app(SelectMultiplayerLobbyProposalAction::class)->handle($host, $lobby->refresh(), $selectedProposal);

    $this->actingAs($host)
        ->get(route('multiplayer.lobbies.show', $lobby))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Arbiter')
            ->where('lobby.members.1.id', $firstMember->id)
            ->where('lobby.members.1.score', 1)
            ->where('lobby.members.1.turns_played', 1)
            ->where('lobby.members.2.id', $secondMember->id)
            ->where('lobby.members.2.score', 0)
            ->where('lobby.members.2.turns_played', 1));
});

it('awards the point when the decision window auto-selects a proposal', function (): void {
    Event::fake([MultiplayerArbiterStateChanged::class]);
    Queue::fake();
    Carbon::setTestNow('2026-09-21 12:00:00');

    try {
        [
            'host' => $host,
            'firstContributor' => $firstContributor,
            'lobby' => $lobby,
            'firstMember' => $firstMember,
            'sourcePrompt' => $sourcePrompt,
        ] = createStartedArbiterLobby();

        $this->withoutVite();

        app(SubmitMultiplayerLobbyProposalAction::class)->handle($firstContributor, $lobby, 'Take the path through the woods.');
        $lobby->refresh();
        $deadlineTimestamp = $lobby->decision_deadline_at?->getTimestamp();

        Carbon::setTestNow('2026-09-21 12:03:01');

        app(ResolveMultiplayerLobbyDecisionDeadlineAction::class)->handle(
            $lobby->id,
            (string) $sourcePrompt->id,
            $deadlineTimestamp,
        );

        $this->actingAs($host)
            ->get(route('multiplayer.lobbies.show', $lobby))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Arbiter')
                ->where('lobby.members.1.id', $firstMember->id)
                ->where('lobby.members.1.score', 1)
                ->where('lobby.members.1.turns_played', 1)
                ->where('lobby.members.2.score', 0)
                ->where('lobby.members.2.turns_played', 0));
    } finally {
        Carbon::setTestNow();
    }
});

it('includes score and turns played in the multiplayer lobby broadcast payload', function (): void {
    Event::fake([MultiplayerArbiterStateChanged::class]);
    Queue::fake();

    [
        'host' => $host,
        'firstContributor' => $firstContributor,
        'lobby' => $lobby,
        'firstMember' => $firstMember,
    ] = createStartedArbiterLobby();

    app(SubmitMultiplayerLobbyProposalAction::class)->handle($firstContributor, $lobby, 'Take the path through the woods.');

    $payload = MultiplayerLobbyBroadcastPayload::forLobby($lobby->refresh()->load('members.user'));
    $firstMemberPayload = collect($payload['members'])->firstWhere('id', $firstMember->id);

    expect($firstMemberPayload)
        ->not->toBeNull()
        ->and($firstMemberPayload['score'])->toBe(0)
        ->and($firstMemberPayload['turns_played'])->toBe(1);

    $hostPayload = collect($payload['members'])->firstWhere('user.id', $host->id);

    expect($hostPayload['score'])->toBe(0)
        ->and($hostPayload['turns_played'])->toBe(0);
});

it('shows score and turns played on arbiter player cards', function (): void {
    $template = file_get_contents(resource_path('js/pages/Arbiter.vue'));

    expect($template)
        ->toContain('v-if="!isRouletteMode"')
        ->toContain('Score: {{ player.score }}')
        ->toContain('Turns Played: {{ player.turnsPlayed }}')
        ->toContain('score: member.score ?? 0')
        ->toContain('turnsPlayed: member.turns_played ?? 0')
        ->toContain('<span v-else class="player-score">{{ player.role }}</span>');
});
