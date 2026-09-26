<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\PlayerTokenUsage;
use App\Models\Story;
use App\Models\User;
use App\Support\GameTurnRequestAssembler;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

it('orders history before a boiler turn and puts the latest action in the final user message', function (): void {
    $user = User::factory()->create();
    $story = Story::factory()->create(['slug' => 'peter-pan']);
    $game = Game::query()->create([
        'story_id' => $story->id,
        'user_id' => $user->id,
        'current_session_number' => 1,
        'model' => 'claude-haiku-4-5',
    ]);

    $openingSource = $game->prompts()->create([
        'session_number' => 1,
        'moment_key' => 'S1_M01',
        'response' => '<p>Opening scene.</p>',
        'choices' => ['A', 'B', 'C'],
    ]);

    $openingSource->update(['prompt' => 'I ask who he is.']);

    $firstResponse = $game->prompts()->create([
        'source_prompt_id' => $openingSource->id,
        'session_number' => 1,
        'moment_key' => 'S1_M01',
        'response' => '<p>Peter answers.</p>',
        'choices' => ['Follow', 'Stay', 'Ask again'],
    ]);

    PlayerTokenUsage::query()->create([
        'game_id' => $game->id,
        'prompt_id' => $firstResponse->id,
        'prompt_reference_id' => $firstResponse->id,
        'story_id' => $story->id,
        'user_id' => $user->id,
        'story_cycle_number' => 1,
        'session_number' => 1,
        'moment_key' => 'S1_M01',
        'source_event_ids' => [],
        'source_chapter_ids' => [],
        'kind' => PlayerTokenUsage::KIND_TURN,
        'system_prompt' => 'SYSTEM AT CALL',
        'generated_output' => $firstResponse->response,
        'provider' => 'anthropic',
        'model' => 'claude-haiku-4-5',
        'currency' => 'USD',
        'pricing_version' => 'test',
        'pricing_effective_from' => now(),
        'pricing_source' => 'test',
        'input_price_per_million_usd' => '1',
        'output_price_per_million_usd' => '1',
        'cache_write_price_per_million_usd' => '0',
        'cache_read_price_per_million_usd' => '0',
        'input_tokens' => 1,
        'output_tokens' => 1,
        'cache_write_input_tokens' => 0,
        'cache_read_input_tokens' => 0,
        'cached_input_tokens' => 0,
        'total_input_tokens' => 1,
        'total_tokens' => 2,
        'reasoning_tokens' => 0,
        'input_cost_usd' => '0',
        'output_cost_usd' => '0',
        'cache_write_cost_usd' => '0',
        'cache_read_cost_usd' => '0',
        'total_cost_usd' => '0',
        'usage_reported' => false,
        'raw_usage' => [],
        'occurred_at' => now(),
    ]);

    $pack = app(GameTurnRequestAssembler::class)->assemble($game->fresh(), $firstResponse->fresh());

    expect($pack['stored_player_input'])->toBe('I ask who he is.')
        ->and($pack['conversation_history_full_before_slice'])->toHaveCount(1)
        ->and($pack['conversation_history_full_before_slice'][0]['role'])->toBe('narrator')
        ->and($pack['conversation_history_full_before_slice'][0]['text'])->toBe('Opening scene.')
        ->and($pack['conversation_history_window'])->toHaveCount(1)
        ->and($pack['prism_messages'])->toHaveCount(2)
        ->and($pack['prism_messages'][1]['role'])->toBe('user')
        ->and($pack['prism_messages'][1]['content'])->toContain("WENDY DARLING'S ACTION: I ask who he is.");
});

it('maps continue marker to the llm player action text', function (): void {
    $user = User::factory()->create();
    $story = Story::factory()->create(['slug' => 'peter-pan']);
    $game = Game::query()->create([
        'story_id' => $story->id,
        'user_id' => $user->id,
        'current_session_number' => 1,
        'model' => 'claude-haiku-4-5',
    ]);

    $source = $game->prompts()->create([
        'session_number' => 1,
        'moment_key' => 'S1_M03',
        'response' => '<p>Still flying.</p>',
        'choices' => [],
    ]);

    $source->update(['prompt' => '__continue__']);

    $response = $game->prompts()->create([
        'source_prompt_id' => $source->id,
        'session_number' => 1,
        'moment_key' => 'S1_M03',
        'response' => '<p>The flight continues.</p>',
        'choices' => [],
    ]);

    PlayerTokenUsage::query()->create([
        'game_id' => $game->id,
        'prompt_id' => $response->id,
        'prompt_reference_id' => $response->id,
        'story_id' => $story->id,
        'user_id' => $user->id,
        'story_cycle_number' => 1,
        'session_number' => 1,
        'moment_key' => 'S1_M03',
        'source_event_ids' => [],
        'source_chapter_ids' => [],
        'kind' => PlayerTokenUsage::KIND_TURN,
        'system_prompt' => 'SYSTEM',
        'generated_output' => $response->response,
        'provider' => 'anthropic',
        'model' => 'claude-haiku-4-5',
        'currency' => 'USD',
        'pricing_version' => 'test',
        'pricing_effective_from' => now(),
        'pricing_source' => 'test',
        'input_price_per_million_usd' => '1',
        'output_price_per_million_usd' => '1',
        'cache_write_price_per_million_usd' => '0',
        'cache_read_price_per_million_usd' => '0',
        'input_tokens' => 1,
        'output_tokens' => 1,
        'cache_write_input_tokens' => 0,
        'cache_read_input_tokens' => 0,
        'cached_input_tokens' => 0,
        'total_input_tokens' => 1,
        'total_tokens' => 2,
        'reasoning_tokens' => 0,
        'input_cost_usd' => '0',
        'output_cost_usd' => '0',
        'cache_write_cost_usd' => '0',
        'cache_read_cost_usd' => '0',
        'total_cost_usd' => '0',
        'usage_reported' => false,
        'raw_usage' => [],
        'occurred_at' => now(),
    ]);

    $pack = app(GameTurnRequestAssembler::class)->assemble($game->fresh(), $response->fresh());

    expect($pack['stored_player_input'])->toBe('__continue__')
        ->and($pack['llm_player_action'])->toBe('Continue the story forward.')
        ->and($pack['prism_messages'][1]['content'])->toContain('Continue the story forward.');
});
