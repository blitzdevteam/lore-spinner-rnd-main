<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('player_token_usage_events')
                ->whereIn('player_token_usage_id', $this->legacyUsageIds())
                ->delete();

            DB::table('player_token_usage_chapters')
                ->whereIn('player_token_usage_id', $this->legacyUsageIds())
                ->delete();

            DB::table('player_token_usages')
                ->whereIn('id', $this->legacyUsageIds())
                ->update([
                    'anchor_event_id' => null,
                    'anchor_chapter_id' => null,
                    'source_event_ids' => json_encode([], JSON_THROW_ON_ERROR),
                    'source_chapter_ids' => json_encode([], JSON_THROW_ON_ERROR),
                ]);
        });
    }

    public function down(): void
    {
        // The removed links were inferred, not observed, so restoring them
        // would reintroduce inaccurate event and chapter attribution.
    }

    private function legacyUsageIds(): Builder
    {
        return DB::table('player_token_usages')
            ->select('id')
            ->whereNull('playable_moment_id')
            ->where(static fn (Builder $query): Builder => $query
                ->whereNull('moment_key')
                ->orWhere('moment_key', ''));
    }
};
