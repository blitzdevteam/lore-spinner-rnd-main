<?php

declare(strict_types=1);

namespace App\Services\Adaptation;

use App\Models\Game;
use App\Models\GameReset;
use Illuminate\Support\Facades\DB;

final class StoryGameResetService
{
    public function reset(Game $game): void
    {
        DB::transaction(function () use ($game): void {
            $lockedGame = Game::query()->whereKey($game->id)->lockForUpdate()->firstOrFail();

            GameReset::create([
                'game_id' => $lockedGame->id,
                'user_id' => $lockedGame->user_id,
                'story_id' => $lockedGame->story_id,
                'had_prior_completion' => $lockedGame->completed_at !== null,
            ]);

            $lockedGame->prompts()->delete();
            $lockedGame->update([
                'current_session_number' => 1,
                'current_story_cycle_number' => $lockedGame->current_story_cycle_number + 1,
                'current_session_complete' => false,
                'world_state' => null,
                'symbolic_memory' => null,
                'alignment_scaffold' => ['chaotic' => 0, 'lawful' => 0, 'neutral' => 0],
                'defining_choice_id' => null,
                'defining_path_label' => null,
                'defining_choice_line' => null,
                'is_climactic_choice' => false,
                'current_playable_session_cut_id' => null,
                'current_playable_moment_id' => null,
                'playable_moment_turn_count' => 0,
                'moment_footnote' => null,
                'completed_at' => null,
            ]);
        });
    }
}
