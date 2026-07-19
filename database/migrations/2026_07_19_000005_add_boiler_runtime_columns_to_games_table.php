<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->foreignId('current_playable_session_cut_id')
                ->nullable()
                ->constrained('playable_session_cuts')
                ->nullOnDelete();
            $table->foreignId('current_playable_moment_id')
                ->nullable()
                ->constrained('playable_moments')
                ->nullOnDelete();
            $table->unsignedInteger('playable_moment_turn_count')->default(0);
            $table->json('moment_footnote')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_playable_moment_id');
            $table->dropConstrainedForeignId('current_playable_session_cut_id');
            $table->dropColumn([
                'playable_moment_turn_count',
                'moment_footnote',
            ]);
        });
    }
};
