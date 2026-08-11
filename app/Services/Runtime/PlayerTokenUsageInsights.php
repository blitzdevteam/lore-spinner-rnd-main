<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use App\Models\PlayerTokenUsage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class PlayerTokenUsageInsights
{
    public const DIMENSION_GAME = 'game';

    public const DIMENSION_PLAYER = 'player';

    public const DIMENSION_SESSION = 'session';

    public const DIMENSION_CHAPTER = 'chapter';

    /**
     * @param  Builder<PlayerTokenUsage>  $query
     * @return array{
     *     requests:int,
     *     input_tokens:int,
     *     cached_input_tokens:int,
     *     total_input_tokens:int,
     *     output_tokens:int,
     *     total_tokens:int,
     *     total_cost_usd:string,
     *     unreported_requests:int,
     *     cache_share:float,
     *     top_session:?array{
     *         key:string,
     *         label:string,
     *         requests:int,
     *         input_tokens:int,
     *         cached_input_tokens:int,
     *         output_tokens:int,
     *         total_tokens:int,
     *         total_cost_usd:string,
     *     },
     * }
     */
    public function overview(Builder $query): array
    {
        $totals = DB::query()
            ->fromSub($this->filteredRows($query, [
                'id',
                'input_tokens',
                'cached_input_tokens',
                'total_input_tokens',
                'output_tokens',
                'total_tokens',
                'total_cost_usd',
                'usage_reported',
            ]), 'usage')
            ->selectRaw(implode(', ', [
                'COUNT(*) AS requests',
                'COALESCE(SUM(input_tokens), 0) AS input_tokens',
                'COALESCE(SUM(cached_input_tokens), 0) AS cached_input_tokens',
                'COALESCE(SUM(total_input_tokens), 0) AS total_input_tokens',
                'COALESCE(SUM(output_tokens), 0) AS output_tokens',
                'COALESCE(SUM(total_tokens), 0) AS total_tokens',
                'COALESCE(SUM(total_cost_usd), 0) AS total_cost_usd',
                'COALESCE(SUM(CASE WHEN usage_reported = false THEN 1 ELSE 0 END), 0) AS unreported_requests',
            ]))
            ->first();

        $totalInputTokens = $this->integer($totals?->total_input_tokens);
        $cachedInputTokens = $this->integer($totals?->cached_input_tokens);

        return [
            'requests' => $this->integer($totals?->requests),
            'input_tokens' => $this->integer($totals?->input_tokens),
            'cached_input_tokens' => $cachedInputTokens,
            'total_input_tokens' => $totalInputTokens,
            'output_tokens' => $this->integer($totals?->output_tokens),
            'total_tokens' => $this->integer($totals?->total_tokens),
            'total_cost_usd' => $this->decimal($totals?->total_cost_usd),
            'unreported_requests' => $this->integer($totals?->unreported_requests),
            'cache_share' => $totalInputTokens > 0
                ? ($cachedInputTokens / $totalInputTokens) * 100
                : 0.0,
            'top_session' => $this->topConsumers(
                $query,
                self::DIMENSION_SESSION,
                1,
            )[0] ?? null,
        ];
    }

    /**
     * Returns the latest active days within the already-filtered ledger query.
     * Days without requests are intentionally omitted rather than rendered as
     * fabricated zero-usage activity.
     *
     * @param  Builder<PlayerTokenUsage>  $query
     * @return array{
     *     labels:array<int, string>,
     *     input_tokens:array<int, int>,
     *     cached_input_tokens:array<int, int>,
     *     output_tokens:array<int, int>,
     * }
     */
    public function dailyTokenMix(Builder $query, int $limit = 30): array
    {
        $dateExpression = 'DATE(usage.occurred_at)';

        $rows = DB::query()
            ->fromSub($this->filteredRows($query, [
                'occurred_at',
                'input_tokens',
                'cached_input_tokens',
                'output_tokens',
            ]), 'usage')
            ->selectRaw(implode(', ', [
                "{$dateExpression} AS usage_date",
                'COALESCE(SUM(input_tokens), 0) AS input_tokens',
                'COALESCE(SUM(cached_input_tokens), 0) AS cached_input_tokens',
                'COALESCE(SUM(output_tokens), 0) AS output_tokens',
            ]))
            ->groupByRaw($dateExpression)
            ->orderByDesc('usage_date')
            ->limit(max(1, $limit))
            ->get()
            ->reverse()
            ->values();

        return [
            'labels' => $rows
                ->map(fn (stdClass $row): string => CarbonImmutable::parse(
                    $this->stringValue($row->usage_date),
                )->format('M j, Y'))
                ->all(),
            'input_tokens' => $rows
                ->map(fn (stdClass $row): int => $this->integer($row->input_tokens))
                ->all(),
            'cached_input_tokens' => $rows
                ->map(fn (stdClass $row): int => $this->integer($row->cached_input_tokens))
                ->all(),
            'output_tokens' => $rows
                ->map(fn (stdClass $row): int => $this->integer($row->output_tokens))
                ->all(),
        ];
    }

    /**
     * Normalize each provider by its number of requests so providers with more
     * traffic do not automatically look less efficient. Requests whose provider
     * omitted usage are excluded because treating them as zero would bias the
     * averages downward.
     *
     * @param  Builder<PlayerTokenUsage>  $query
     * @return array<int, array{
     *     provider:string,
     *     label:string,
     *     requests:int,
     *     average_input_tokens:int,
     *     average_cached_input_tokens:int,
     *     average_output_tokens:int,
     *     average_total_tokens:int,
     *     average_cost_usd:float,
     * }>
     */
    public function providerAverages(Builder $query): array
    {
        $rows = DB::query()
            ->fromSub($this->filteredRows($query, [
                'provider',
                'input_tokens',
                'cached_input_tokens',
                'output_tokens',
                'total_tokens',
                'total_cost_usd',
                'usage_reported',
            ]), 'usage')
            ->where('usage_reported', true)
            ->selectRaw(implode(', ', [
                'provider',
                'COUNT(*) AS requests',
                'COALESCE(SUM(input_tokens), 0) AS input_tokens',
                'COALESCE(SUM(cached_input_tokens), 0) AS cached_input_tokens',
                'COALESCE(SUM(output_tokens), 0) AS output_tokens',
                'COALESCE(SUM(total_tokens), 0) AS total_tokens',
                'COALESCE(SUM(total_cost_usd), 0) AS total_cost_usd',
            ]))
            ->groupBy('provider')
            ->orderBy('provider')
            ->get();

        return $rows
            ->map(function (stdClass $row): array {
                $requests = max(1, $this->integer($row->requests));
                $provider = $this->text($row->provider, 'unknown');

                return [
                    'provider' => $provider,
                    'label' => $this->providerLabel($provider).' · '.number_format($requests).' req',
                    'requests' => $requests,
                    'average_input_tokens' => (int) round($this->integer($row->input_tokens) / $requests),
                    'average_cached_input_tokens' => (int) round($this->integer($row->cached_input_tokens) / $requests),
                    'average_output_tokens' => (int) round($this->integer($row->output_tokens) / $requests),
                    'average_total_tokens' => (int) round($this->integer($row->total_tokens) / $requests),
                    'average_cost_usd' => (float) $this->decimal($row->total_cost_usd) / $requests,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Builder<PlayerTokenUsage>  $query
     * @return array<int, array{
     *     key:string,
     *     label:string,
     *     requests:int,
     *     input_tokens:int,
     *     cached_input_tokens:int,
     *     output_tokens:int,
     *     total_tokens:int,
     *     total_cost_usd:string,
     * }>
     */
    public function topConsumers(
        Builder $query,
        string $dimension,
        int $limit = 8,
    ): array {
        $limit = max(1, min($limit, 25));

        return match ($dimension) {
            self::DIMENSION_PLAYER => $this->topPlayers($query, $limit),
            self::DIMENSION_SESSION => $this->topSessions($query, $limit),
            self::DIMENSION_CHAPTER => $this->topChapters($query, $limit),
            default => $this->topGames($query, $limit),
        };
    }

    /**
     * @param  Builder<PlayerTokenUsage>  $query
     * @return array<int, array{key:string,label:string,requests:int,input_tokens:int,cached_input_tokens:int,output_tokens:int,total_tokens:int,total_cost_usd:string}>
     */
    private function topGames(Builder $query, int $limit): array
    {
        $rows = $this->withMetrics(
            DB::query()
                ->fromSub($this->breakdownRows($query), 'usage')
                ->leftJoin('stories as story', 'story.id', '=', 'usage.story_id')
                ->leftJoin('users as player', 'player.id', '=', 'usage.user_id')
                ->select([
                    'usage.game_id as dimension_key',
                    'story.title as story_title',
                    'player.email as player_email',
                ])
                ->groupBy([
                    'usage.game_id',
                    'story.title',
                    'player.email',
                ]),
        )
            ->orderByDesc('total_tokens')
            ->limit($limit)
            ->get();

        return $this->normalizeRows(
            $rows,
            fn (stdClass $row): string => Str::limit(implode(' · ', array_filter([
                '#'.$this->shortGameId($this->stringValue($row->dimension_key)),
                $this->text($row->story_title, 'Untitled story'),
                $this->text($row->player_email, 'Unknown player'),
            ])), 72),
        );
    }

    /**
     * @param  Builder<PlayerTokenUsage>  $query
     * @return array<int, array{key:string,label:string,requests:int,input_tokens:int,cached_input_tokens:int,output_tokens:int,total_tokens:int,total_cost_usd:string}>
     */
    private function topPlayers(Builder $query, int $limit): array
    {
        $rows = $this->withMetrics(
            DB::query()
                ->fromSub($this->breakdownRows($query), 'usage')
                ->leftJoin('users as player', 'player.id', '=', 'usage.user_id')
                ->select([
                    'usage.user_id as dimension_key',
                    'player.first_name',
                    'player.last_name',
                    'player.email as player_email',
                ])
                ->groupBy([
                    'usage.user_id',
                    'player.first_name',
                    'player.last_name',
                    'player.email',
                ]),
        )
            ->orderByDesc('total_tokens')
            ->limit($limit)
            ->get();

        return $this->normalizeRows($rows, function (stdClass $row): string {
            $name = mb_trim(implode(' ', array_filter([
                $this->text($row->first_name),
                $this->text($row->last_name),
            ])));
            $email = $this->text($row->player_email, 'Unknown player');

            return Str::limit($name === '' ? $email : "{$name} · {$email}", 72);
        });
    }

    /**
     * @param  Builder<PlayerTokenUsage>  $query
     * @return array<int, array{key:string,label:string,requests:int,input_tokens:int,cached_input_tokens:int,output_tokens:int,total_tokens:int,total_cost_usd:string}>
     */
    private function topSessions(Builder $query, int $limit): array
    {
        $rows = $this->withMetrics(
            DB::query()
                ->fromSub($this->breakdownRows($query), 'usage')
                ->leftJoin('stories as story', 'story.id', '=', 'usage.story_id')
                ->select([
                    'usage.game_id as dimension_key',
                    'usage.game_id',
                    'usage.story_cycle_number',
                    'usage.session_number',
                    'story.title as story_title',
                ])
                ->groupBy([
                    'usage.game_id',
                    'usage.story_cycle_number',
                    'usage.session_number',
                    'story.title',
                ]),
        )
            ->orderByDesc('total_tokens')
            ->limit($limit)
            ->get();

        return $this->normalizeRows(
            $rows,
            fn (stdClass $row): string => Str::limit(implode(' · ', [
                '#'.$this->shortGameId($this->stringValue($row->game_id)),
                $this->text($row->story_title, 'Untitled story'),
                "C{$this->integer($row->story_cycle_number)} / S{$this->integer($row->session_number)}",
            ]), 72),
        );
    }

    /**
     * @param  Builder<PlayerTokenUsage>  $query
     * @return array<int, array{key:string,label:string,requests:int,input_tokens:int,cached_input_tokens:int,output_tokens:int,total_tokens:int,total_cost_usd:string}>
     */
    private function topChapters(Builder $query, int $limit): array
    {
        $rows = $this->withMetrics(
            DB::query()
                ->fromSub($this->breakdownRows($query), 'usage')
                ->join(
                    'player_token_usage_chapters as usage_chapter',
                    'usage_chapter.player_token_usage_id',
                    '=',
                    'usage.id',
                )
                ->join('chapters as chapter', 'chapter.id', '=', 'usage_chapter.chapter_id')
                ->leftJoin('stories as story', 'story.id', '=', 'chapter.story_id')
                ->select([
                    'chapter.id as dimension_key',
                    'chapter.position as chapter_position',
                    'chapter.title as chapter_title',
                    'story.title as story_title',
                ])
                ->groupBy([
                    'chapter.id',
                    'chapter.position',
                    'chapter.title',
                    'story.title',
                ]),
        )
            ->orderByDesc('total_tokens')
            ->limit($limit)
            ->get();

        return $this->normalizeRows(
            $rows,
            fn (stdClass $row): string => Str::limit(implode(' · ', [
                $this->text($row->story_title, 'Untitled story'),
                'Chapter '.$this->integer($row->chapter_position),
                $this->text($row->chapter_title, 'Untitled chapter'),
            ]), 72),
        );
    }

    /** @param Builder<PlayerTokenUsage> $query */
    private function breakdownRows(Builder $query): QueryBuilder
    {
        return $this->filteredRows($query, [
            'id',
            'game_id',
            'story_id',
            'user_id',
            'story_cycle_number',
            'session_number',
            'input_tokens',
            'cached_input_tokens',
            'output_tokens',
            'total_tokens',
            'total_cost_usd',
        ]);
    }

    private function withMetrics(QueryBuilder $query): QueryBuilder
    {
        return $query->selectRaw(implode(', ', [
            'COUNT(*) AS requests',
            'COALESCE(SUM(usage.input_tokens), 0) AS input_tokens',
            'COALESCE(SUM(usage.cached_input_tokens), 0) AS cached_input_tokens',
            'COALESCE(SUM(usage.output_tokens), 0) AS output_tokens',
            'COALESCE(SUM(usage.total_tokens), 0) AS total_tokens',
            'COALESCE(SUM(usage.total_cost_usd), 0) AS total_cost_usd',
        ]));
    }

    /**
     * @param  Builder<PlayerTokenUsage>  $query
     * @param  array<int, string>  $columns
     */
    private function filteredRows(Builder $query, array $columns): QueryBuilder
    {
        return (clone $query)
            ->reorder()
            ->select(array_map(
                static fn (string $column): string => "player_token_usages.{$column}",
                $columns,
            ))
            ->toBase();
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @param  callable(stdClass): string  $label
     * @return array<int, array{key:string,label:string,requests:int,input_tokens:int,cached_input_tokens:int,output_tokens:int,total_tokens:int,total_cost_usd:string}>
     */
    private function normalizeRows(Collection $rows, callable $label): array
    {
        return $rows
            ->map(fn (stdClass $row): array => [
                'key' => $this->stringValue($row->dimension_key),
                'label' => $label($row),
                'requests' => $this->integer($row->requests),
                'input_tokens' => $this->integer($row->input_tokens),
                'cached_input_tokens' => $this->integer($row->cached_input_tokens),
                'output_tokens' => $this->integer($row->output_tokens),
                'total_tokens' => $this->integer($row->total_tokens),
                'total_cost_usd' => $this->decimal($row->total_cost_usd),
            ])
            ->values()
            ->all();
    }

    private function shortGameId(string $gameId): string
    {
        return Str::upper(Str::substr($gameId, 0, 8));
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function decimal(mixed $value): string
    {
        return is_numeric($value) ? (string) $value : '0';
    }

    private function text(mixed $value, string $fallback = ''): string
    {
        return is_string($value) && mb_trim($value) !== '' ? mb_trim($value) : $fallback;
    }

    private function providerLabel(string $provider): string
    {
        return match (mb_strtolower($provider)) {
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            default => Str::headline($provider),
        };
    }

    private function stringValue(mixed $value, string $fallback = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $fallback;
    }
}
