<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CompletionReceiptMode;
use App\Models\CompletionReceipt;
use App\Models\Game;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompletionReceipt>
 */
final class CompletionReceiptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $story = Story::factory()->create();
        $user = User::factory()->withCompleteProfile()->create([
            'is_active' => true,
        ]);
        $game = Game::query()->create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'current_session_number' => 1,
            'current_session_complete' => true,
            'model' => 'claude-sonnet-4-6',
        ]);

        return [
            'user_id' => $user->id,
            'game_id' => $game->id,
            'story_id' => $story->id,
            'story_cycle_number' => 1,
            'session_number' => 1,
            'mode' => CompletionReceiptMode::Session,
            'receipt' => [
                'mode' => CompletionReceiptMode::Session->value,
                'story_title' => $story->title,
                'session_number' => 1,
                'final_line' => 'The night still holds what you chose.',
                'share_line' => 'The night still holds what you chose.',
            ],
        ];
    }

    public function storyEnd(): static
    {
        return $this->state(fn (array $attributes): array => [
            'mode' => CompletionReceiptMode::Story,
            'receipt' => array_merge((array) $attributes['receipt'], [
                'mode' => CompletionReceiptMode::Story->value,
            ]),
        ]);
    }
}
