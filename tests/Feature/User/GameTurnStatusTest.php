<?php

declare(strict_types=1);

use App\Actions\Game\ProcessGameTurnAction;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Game;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();

    $this->user = User::factory()->withCompleteProfile()->create(['is_active' => true]);
    $story = Story::factory()->create([
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);
    $this->game = Game::query()->create([
        'story_id' => $story->id,
        'user_id' => $this->user->id,
        'current_session_number' => 1,
        'model' => 'claude-sonnet-4-6',
    ]);
    $this->source = $this->game->prompts()->create([
        'session_number' => 1,
        'response' => '<p>A door stands ajar.</p>',
        'choices' => ['Open it', 'Knock', 'Walk away'],
    ]);
});

function turnStatus(User $user, Game $game, ?string $sourcePromptId)
{
    return test()->actingAs($user, 'user')->getJson(
        route('user.games.turn-status', $game).($sourcePromptId ? '?source_prompt_id='.$sourcePromptId : ''),
    );
}

it('reports an in-flight turn as pending with no committed row yet', function (): void {
    $turnAction = app(ProcessGameTurnAction::class);
    Cache::put($turnAction->pendingKey((string) $this->game->id), (string) $this->source->id, 180);

    turnStatus($this->user, $this->game, $this->source->id)
        ->assertOk()
        ->assertJson([
            'pending' => true,
            'pending_source_prompt_id' => (string) $this->source->id,
            'error' => null,
            'source_prompt_id' => (string) $this->source->id,
            'source_prompt_value' => null,
            'prompt' => null,
            'game' => [
                'current_session_number' => 1,
                'current_session_complete' => false,
                'uses_playable_moments' => false,
            ],
        ]);
});

it('returns the committed narration once the turn has landed', function (): void {
    $this->source->update(['prompt' => 'I push the door open slowly.']);
    $committed = $this->game->prompts()->create([
        'source_prompt_id' => $this->source->id,
        'session_number' => 1,
        'response' => '<p>The hinge gives.</p>',
        'choices' => [
            ['text' => 'Step through', 'canonical_choice_id' => 'S1_C02', 'canonical_path_label' => 'B'],
        ],
    ]);
    $this->game->update(['current_session_complete' => true]);

    turnStatus($this->user, $this->game, $this->source->id)
        ->assertOk()
        ->assertJsonPath('pending', false)
        ->assertJsonPath('source_prompt_value', 'I push the door open slowly.')
        ->assertJsonPath('prompt.id', (string) $committed->id)
        ->assertJsonPath('prompt.response', '<p>The hinge gives.</p>')
        ->assertJsonPath('prompt.choices.0.canonical_path_label', 'B')
        ->assertJsonPath('game.current_session_complete', true);
});

it('surfaces the recorded failure when the narrator gave up', function (): void {
    $turnAction = app(ProcessGameTurnAction::class);
    Cache::put($turnAction->errorKey((string) $this->game->id), 'Narration hiccuped — please retry this turn.', 60);

    turnStatus($this->user, $this->game, $this->source->id)
        ->assertOk()
        ->assertJsonPath('pending', false)
        ->assertJsonPath('error', 'Narration hiccuped — please retry this turn.')
        ->assertJsonPath('prompt', null);
});

it('answers without a prompt lookup when no source prompt is given', function (): void {
    turnStatus($this->user, $this->game, null)
        ->assertOk()
        ->assertJsonPath('source_prompt_id', null)
        ->assertJsonPath('prompt', null);
});

it('refuses to report on another player\'s game', function (): void {
    $stranger = User::factory()->withCompleteProfile()->create(['is_active' => true]);

    turnStatus($stranger, $this->game, $this->source->id)->assertForbidden();
});
