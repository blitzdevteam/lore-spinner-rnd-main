<?php

declare(strict_types=1);

namespace App\Filament\Manager\Widgets\Analytics;

use App\Support\Analytics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Retention KPI cards.
 *
 * Cohort anchor: first observed playable experience — MIN(game_session_completions.started_at)
 * per user, on or after the analytics baseline. Excludes users who created a
 * game but never received narration. Each D-N metric uses only cohorts old
 * enough to have reached that milestone.
 *
 * D1: returned the next calendar day after first playable session.
 * D7: returned any day within 7 days of first playable session.
 * D30: returned any day within 30 days of first playable session.
 * Return Rate: % of users with more than one distinct activity day.
 */
final class AnalyticsRetentionWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Retention';

    protected ?string $description = null;

    protected function getDescription(): ?string
    {
        return 'Cohort: users whose first observed gameplay session (Session 1 narration delivered) is on or after '
            .Analytics::baselineLabel()
            .'. D1/D7/D30 use only mature cohorts.';
    }

    protected function getStats(): array
    {
        try {
            $retention = $this->retentionStats();
            $returnRate = $this->returnRate();
        } catch (Throwable) {
            return [
                Stat::make('Retention unavailable', 'Run migrations')
                    ->description('One or more analytics tables are missing. Run: php artisan migrate')
                    ->color('warning'),
            ];
        }

        $d1CohortSize = (int) ($retention->d1_cohort_size ?? 0);
        $d7CohortSize = (int) ($retention->d7_cohort_size ?? 0);
        $d30CohortSize = (int) ($retention->d30_cohort_size ?? 0);

        $fmt = static function (int $cohort, int $retained): string {
            if ($cohort === 0) {
                return 'N/A';
            }

            return round($retained / $cohort * 100, 1).'%';
        };

        return [
            Stat::make('D1 Retention', $fmt($d1CohortSize, (int) ($retention->d1_retained ?? 0)))
                ->description('Cohort: '.number_format($d1CohortSize).' users · returned next day')
                ->color('primary'),

            Stat::make('D7 Retention', $fmt($d7CohortSize, (int) ($retention->d7_retained ?? 0)))
                ->description('Cohort: '.number_format($d7CohortSize).' users · returned within 7 days')
                ->color('info'),

            Stat::make('D30 Retention', $fmt($d30CohortSize, (int) ($retention->d30_retained ?? 0)))
                ->description('Cohort: '.number_format($d30CohortSize).' users · returned within 30 days')
                ->color('warning'),

            Stat::make('Return Rate', $returnRate)
                ->description('Users with more than one active day after first play (since '.Analytics::baselineLabel().')')
                ->color('success'),
        ];
    }

    /**
     * Computes all three retention rates in a single pass.
     *
     * Cohort: users whose first session started_at is >= analytics baseline.
     */
    private function retentionStats(): object
    {
        $asOfDateTime = Analytics::asOfDateTimeString();
        $asOfDate = Analytics::asOf()->toDateString();

        $result = DB::select("
            WITH cohort AS (
                SELECT gsc.user_id, MIN(gsc.started_at)::date AS first_play
                FROM game_session_completions gsc
                JOIN games g ON g.id = gsc.game_id
                WHERE g.is_preview = false
                  AND gsc.started_at IS NOT NULL
                  AND gsc.started_at <= ?
                GROUP BY gsc.user_id
                HAVING MIN(gsc.started_at) >= ?
            )
            SELECT
                COUNT(DISTINCT CASE WHEN c.first_play <= ?::date - INTERVAL '1 day' THEN c.user_id END) AS d1_cohort_size,
                COUNT(DISTINCT CASE WHEN c.first_play <= ?::date - INTERVAL '1 day' AND d1.user_id IS NOT NULL THEN c.user_id END) AS d1_retained,
                COUNT(DISTINCT CASE WHEN c.first_play <= ?::date - INTERVAL '7 days' THEN c.user_id END) AS d7_cohort_size,
                COUNT(DISTINCT CASE WHEN c.first_play <= ?::date - INTERVAL '7 days' AND d7.user_id IS NOT NULL THEN c.user_id END) AS d7_retained,
                COUNT(DISTINCT CASE WHEN c.first_play <= ?::date - INTERVAL '30 days' THEN c.user_id END) AS d30_cohort_size,
                COUNT(DISTINCT CASE WHEN c.first_play <= ?::date - INTERVAL '30 days' AND d30.user_id IS NOT NULL THEN c.user_id END) AS d30_retained
            FROM cohort c
            LEFT JOIN user_activity_days d1
                ON  d1.user_id       = c.user_id
                AND d1.activity_date = c.first_play + INTERVAL '1 day'
            LEFT JOIN user_activity_days d7
                ON  d7.user_id       = c.user_id
                AND d7.activity_date BETWEEN c.first_play + INTERVAL '1 day'
                                         AND c.first_play + INTERVAL '7 days'
            LEFT JOIN user_activity_days d30
                ON  d30.user_id       = c.user_id
                AND d30.activity_date BETWEEN c.first_play + INTERVAL '1 day'
                                          AND c.first_play + INTERVAL '30 days'
        ", [
            $asOfDateTime,
            Analytics::baselineDateTimeString(),
            $asOfDate,
            $asOfDate,
            $asOfDate,
            $asOfDate,
            $asOfDate,
            $asOfDate,
        ]);

        return $result[0] ?? (object) [
            'd1_cohort_size' => 0,
            'd1_retained' => 0,
            'd7_cohort_size' => 0,
            'd7_retained' => 0,
            'd30_cohort_size' => 0,
            'd30_retained' => 0,
        ];
    }

    /**
     * Return Rate: % of cohort users with more than one distinct activity day.
     * Cohort = users with at least one started session on or after baseline.
     */
    private function returnRate(): string
    {
        $asOfDateTime = Analytics::asOfDateTimeString();
        $asOfDate = Analytics::asOf()->toDateString();

        $row = DB::select('
            WITH cohort AS (
                SELECT gsc.user_id, MIN(gsc.started_at)::date AS first_play
                FROM game_session_completions gsc
                JOIN games g ON g.id = gsc.game_id
                WHERE g.is_preview = false
                  AND gsc.started_at IS NOT NULL
                  AND gsc.started_at <= ?
                GROUP BY gsc.user_id
                HAVING MIN(gsc.started_at) >= ?
            )
            SELECT
                COUNT(DISTINCT c.user_id) AS total_users,
                COUNT(DISTINCT CASE WHEN a.day_count > 1 THEN c.user_id END) AS returners
            FROM cohort c
            LEFT JOIN (
                SELECT c2.user_id, COUNT(DISTINCT a2.activity_date) AS day_count
                FROM cohort c2
                JOIN user_activity_days a2
                    ON a2.user_id = c2.user_id
                   AND a2.activity_date >= c2.first_play
                   AND a2.activity_date <= ?::date
                GROUP BY c2.user_id
            ) a ON a.user_id = c.user_id
        ', [
            $asOfDateTime,
            Analytics::baselineDateTimeString(),
            $asOfDate,
        ]);

        $data = $row[0] ?? null;

        if (! $data || (int) $data->total_users === 0) {
            return 'N/A';
        }

        return round((int) $data->returners / (int) $data->total_users * 100, 1).'%';
    }
}
