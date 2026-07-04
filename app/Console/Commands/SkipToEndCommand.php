<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Story;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Fast-forward a user's game to the last N sessions so the outro can be tested
 * without having to play through the entire story.
 *
 * The command:
 *  1. Finds the game for the given user + story.
 *  2. Determines the total number of adapted sessions.
 *  3. Deletes all prompts beyond session (total - N + 1) so only the last N
 *     sessions' worth of prompts remain — or clears everything if the game has
 *     not started yet.
 *  4. Sets current_session_number to (total - N + 1) and marks the session as
 *     incomplete so the player can continue from there.
 *
 * Usage:
 *   php artisan game:skip-to-end --user=1 --story=the-adventure-of-the-speckled-band
 *   php artisan game:skip-to-end --user=1 --story=the-wonderful-wizard-of-oz --remaining=1
 */
final class SkipToEndCommand extends Command
{
    protected $signature = 'game:skip-to-end
                            {--user=    : User ID}
                            {--story=   : Story slug or ID}
                            {--remaining=1 : How many sessions to leave remaining (default 1)}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Fast-forward a game to the last N sessions for outro testing';

    public function handle(): int
    {
        $user  = $this->resolveUser();
        $story = $this->resolveStory();

        if (! $user || ! $story) {
            return self::FAILURE;
        }

        $game = Game::where('user_id', $user->id)
            ->where('story_id', $story->id)
            ->first();

        if (! $game) {
            $this->error("No game found for user [{$user->id}] and story [{$story->slug}].");
            $this->line('  → Start a game from the frontend first, then run this command.');

            return self::FAILURE;
        }

        // Load total session count from the adaptation
        $story->load('adaptation.sessionAdaptations');
        $totalSessions = (int) ($story->adaptation?->sessionAdaptations?->count() ?? 0);

        if ($totalSessions === 0) {
            $this->error("Story [{$story->slug}] has no adapted sessions. Run the adaptation pipeline first.");

            return self::FAILURE;
        }

        $remaining = max(1, (int) ($this->option('remaining') ?? 3));

        // The session that will be stubbed as already "complete".
        // remaining=1 → lastCompleted=totalSessions  → one click fires the outro directly
        // remaining=2 → lastCompleted=totalSessions-1 → one real session, then outro
        // remaining=3 → lastCompleted=totalSessions-2 → two real sessions, then outro
        $lastCompleted = max(0, $totalSessions - $remaining + 1);

        // firstRemaining is only used for the deleted-prompt range label
        $firstRemaining = $lastCompleted + 1;

        $this->info("Story:          {$story->title}");
        $this->info("Total sessions: {$totalSessions}");
        $this->info("Remaining:      {$remaining} session(s) to play (sessions {$firstRemaining}–{$totalSessions})");
        $this->info("Last completed: ".($lastCompleted > 0 ? "session {$lastCompleted}" : 'none (game will reset to beginning)'));
        $this->info("Game ID:        {$game->id}");
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm("Fast-forward this game? Prompts from session {$firstRemaining} onwards will be deleted.")) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        // Delete all prompts from firstRemaining onwards so the engine re-generates them
        $deleted = $game->prompts()
            ->where('session_number', '>=', $firstRemaining)
            ->delete();

        if ($lastCompleted > 0) {
            // Ensure the previous session prompt exists so hasPrompts=true on the frontend
            // (prevents the intro cinematic from re-playing).
            $hasLastPrompt = $game->prompts()
                ->where('session_number', $lastCompleted)
                ->exists();

            if (! $hasLastPrompt) {
                $game->prompts()->create([
                    'session_number' => $lastCompleted,
                    'response'       => '[skipped for testing]',
                    'choices'        => [],
                ]);
                $this->warn("No prompt existed for session {$lastCompleted} — created a stub so the game UI loads correctly.");
            }

            $game->update([
                'current_session_number'   => $lastCompleted,
                'current_session_complete' => true,
            ]);
        } else {
            // firstRemaining=1 means we're resetting to the very start;
            // the intro cinematic will play and session 1 will be generated fresh.
            $game->prompts()->delete();
            $game->update([
                'current_session_number'   => 1,
                'current_session_complete' => false,
                'world_state'              => null,
                'alignment_scaffold'       => null,
                'symbolic_memory'          => null,
                'is_climactic_choice'      => false,
                'defining_choice_id'       => null,
                'defining_path_label'      => null,
                'defining_choice_line'     => null,
            ]);
            $this->warn('remaining >= total sessions — game fully reset to session 1 (intro will replay).');
        }

        $this->info("Deleted {$deleted} prompt(s) from session {$firstRemaining}+.");
        $this->info("Game set to: session ".($lastCompleted > 0 ? "{$lastCompleted} (complete)" : '1 (not started)')." / {$totalSessions} total.");
        $this->newLine();
        $this->line('→ Visit the game in the browser and click "Next Session" to play through the remaining sessions and trigger the outro.');

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $value = $this->option('user');

        if (! $value) {
            $this->error('--user is required.');

            return null;
        }

        $user = User::find((int) $value);

        if (! $user) {
            $this->error("User [{$value}] not found.");

            return null;
        }

        return $user;
    }

    private function resolveStory(): ?Story
    {
        $value = $this->option('story');

        if (! $value) {
            $this->error('--story is required.');

            return null;
        }

        $story = is_numeric($value)
            ? Story::find((int) $value)
            : Story::where('slug', $value)->first();

        if (! $story) {
            $this->error("Story [{$value}] not found.");

            return null;
        }

        return $story;
    }
}
