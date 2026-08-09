<?php

declare(strict_types=1);

use App\Models\GameCompletion;
use App\Models\GameReset;
use App\Support\Analytics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\Analytics\AnalyticsTestContext;
use Tests\Support\Analytics\AnalyticsTestLogger;

beforeEach(function () {
    $this->ctx = AnalyticsTestContext::make();
});

afterEach(function () {
    $this->ctx->cleanup();
});

/**
 * Mirrors AnalyticsKpiWidget abandoned query for direct verification.
 */
function countAbandonedGames(): int
{
    $baseline = Analytics::baselineDateTimeString();
    $cutoff = Analytics::abandonedCutoff()->toDateTimeString();

    $result = DB::select('
        SELECT COUNT(*) AS cnt
        FROM (
            SELECT g.id,
                GREATEST(
                    g.created_at,
                    MAX(p.created_at),
                    MAX(gsc.started_at),
                    MAX(gsc.completed_at),
                    MAX(gr.created_at)
                ) AS last_active_at
            FROM games g
            LEFT JOIN prompts p ON p.game_id = g.id
            LEFT JOIN game_session_completions gsc ON gsc.game_id = g.id
            LEFT JOIN game_resets gr ON gr.game_id = g.id
            WHERE g.is_preview = false
              AND g.completed_at IS NULL
              AND g.created_at >= ?
            GROUP BY g.id, g.created_at
        ) sub
        WHERE last_active_at <= ?
    ', [$baseline, $cutoff]);

    return (int) ($result[0]->cnt ?? 0);
}

describe('incomplete vs abandoned', function () {
    it('counts active incomplete games separately from abandoned games', function () {
        Carbon::setTestNow('2026-06-20 12:00:00');

        $user = $this->ctx->createUser();
        $story = $this->ctx->createStory();

        $active = $this->ctx->createGame($user, $story, [
            'created_at' => Carbon::parse('2026-06-18'),
            'completed_at' => null,
        ]);
        $this->ctx->startSession($active, startedAt: Carbon::parse('2026-06-19'));
        $this->ctx->recordPrompt($active, Carbon::parse('2026-06-19'));

        $abandoned = $this->ctx->createGame($user, $story, [
            'created_at' => Carbon::parse('2026-06-02'),
            'completed_at' => null,
        ]);
        $this->ctx->startSession($abandoned, startedAt: Carbon::parse('2026-06-02'));
        $this->ctx->recordPrompt($abandoned, Carbon::parse('2026-06-02'));

        $boundary = $this->ctx->createGame($user, $story, [
            'created_at' => Carbon::parse('2026-06-06 12:00:00'),
            'completed_at' => null,
        ]);
        $this->ctx->startSession($boundary, startedAt: Carbon::parse('2026-06-06 12:00:00'));
        $this->ctx->recordPrompt($boundary, Carbon::parse('2026-06-06 12:00:00'));

        $incompleteCount = DB::table('games')
            ->where('is_preview', false)
            ->whereNull('completed_at')
            ->where('created_at', '>=', Analytics::baselineDateTimeString())
            ->count();

        $abandonedCount = countAbandonedGames();

        expect($incompleteCount)->toBe(3);
        expect($abandonedCount)->toBe(2);

        AnalyticsTestLogger::log('incomplete_vs_abandoned', [
            'now' => now()->toIso8601String(),
            'incomplete_count' => $incompleteCount,
            'abandoned_count' => $abandonedCount,
            'active_game_id' => $active->id,
            'abandoned_game_id' => $abandoned->id,
            'tracked' => $this->ctx->trackedCounts(),
        ]);

        Carbon::setTestNow();
    });
});

describe('completion and replay metrics', function () {
    it('separates completion events from unique completed games', function () {
        $user = $this->ctx->createUser();
        $story = $this->ctx->createStory();
        $game = $this->ctx->createGame($user, $story);

        $this->ctx->recordCompletion($game, storyCycleNumber: 1);
        $this->ctx->recordCompletion($game, storyCycleNumber: 2);

        $starts = DB::table('games')
            ->where('is_preview', false)
            ->where('created_at', '>=', Analytics::baselineDateTimeString())
            ->count();

        $events = GameCompletion::query()
            ->where('completed_at', '>=', Analytics::baseline())
            ->count();

        $unique = (int) GameCompletion::query()
            ->where('completed_at', '>=', Analytics::baseline())
            ->distinct('game_id')
            ->count('game_id');

        $completionRate = $starts > 0 ? round($unique / $starts * 100, 1) : null;

        expect($starts)->toBe(1);
        expect($events)->toBe(2);
        expect($unique)->toBe(1);
        expect($completionRate)->toBe(100.0);

        AnalyticsTestLogger::log('completion_rate_semantics', [
            'starts' => $starts,
            'completion_events' => $events,
            'unique_completed' => $unique,
            'completion_rate' => $completionRate,
            'tracked' => $this->ctx->trackedCounts(),
        ]);
    });

    it('separates replay events from unique replayers', function () {
        $user = $this->ctx->createUser();
        $story = $this->ctx->createStory();
        $game = $this->ctx->createGame($user, $story);

        $this->ctx->recordCompletion($game, storyCycleNumber: 1);

        $this->ctx->recordReplay($game, Carbon::parse('2026-06-05 10:00:00'));
        $this->ctx->recordReplay($game, Carbon::parse('2026-06-06 10:00:00'));
        $this->ctx->recordReplay($game, Carbon::parse('2026-06-07 10:00:00'));

        $events = GameReset::query()
            ->where('had_prior_completion', true)
            ->where('created_at', '>=', Analytics::baseline())
            ->count();

        $uniqueReplayers = (int) GameReset::query()
            ->where('had_prior_completion', true)
            ->where('created_at', '>=', Analytics::baseline())
            ->distinct('user_id')
            ->count('user_id');

        expect($events)->toBe(3);
        expect($uniqueReplayers)->toBe(1);

        AnalyticsTestLogger::log('replay_semantics', [
            'replay_events' => $events,
            'unique_replayers' => $uniqueReplayers,
            'game_id' => $game->id,
            'tracked' => $this->ctx->trackedCounts(),
        ]);
    });
});

describe('retention cohort anchor', function () {
    it('uses first session started_at not bare game creation', function () {
        $user = $this->ctx->createUser();

        $neverPlayed = $this->ctx->createGame($user, $this->ctx->createStory(), [
            'created_at' => Carbon::parse('2026-06-03'),
        ]);

        $played = $this->ctx->createGame($user, $this->ctx->createStory(), [
            'created_at' => Carbon::parse('2026-06-03'),
        ]);
        $this->ctx->startSession($played, startedAt: Carbon::parse('2026-06-04 09:00:00'));

        // Count distinct game rows (not distinct users) to show that naive
        // "games created" overcounts vs "sessions actually started".
        $cohortFromGames = DB::selectOne('
            SELECT COUNT(DISTINCT id) AS cnt
            FROM games
            WHERE is_preview = false AND created_at >= ?
        ', [Analytics::baselineDateTimeString()])->cnt;

        $cohortFromSessions = DB::selectOne('
            SELECT COUNT(DISTINCT gsc.user_id) AS cnt
            FROM game_session_completions gsc
            JOIN games g ON g.id = gsc.game_id
            WHERE g.is_preview = false
              AND gsc.started_at IS NOT NULL
              AND gsc.started_at >= ?
        ', [Analytics::baselineDateTimeString()])->cnt;

        expect($cohortFromGames)->toBe(2);
        expect($cohortFromSessions)->toBe(1);

        AnalyticsTestLogger::log('retention_cohort_anchor', [
            'cohort_from_games_created' => (int) $cohortFromGames,
            'cohort_from_first_session' => (int) $cohortFromSessions,
            'never_played_game_id' => $neverPlayed->id,
            'played_game_id' => $played->id,
            'tracked' => $this->ctx->trackedCounts(),
        ]);
    });
});
