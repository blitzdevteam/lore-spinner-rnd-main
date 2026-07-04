<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V2.3.3 — Deterministic Branch Identity.
 *
 * Adds `defining_path_label` to `games` so the canonical path (A/B/C)
 * chosen by the player is persisted directly, replacing the V2.3.2
 * fuzzy text-match resolution that re-derived it from `defining_choice_line`.
 *
 * The three columns form an atomic triple:
 *   - defining_choice_id       Phase 5 choice_id (e.g. "S2_C3")
 *   - defining_path_label      A | B | C — the chosen authored path
 *   - defining_choice_line     Phase 5 Task 8 defining line (Social Echo)
 *
 * They are always committed together or not at all. The new column defaults
 * to null; existing games carry forward cleanly — the next branch turn
 * populates it. No backfill required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->string('defining_path_label', 1)->nullable()->after('defining_choice_line');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn('defining_path_label');
        });
    }
};
