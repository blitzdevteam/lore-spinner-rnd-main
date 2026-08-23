<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('multiplayer_lobbies', function (Blueprint $table): void {
            $table->foreignUlid('game_id')
                ->nullable()
                ->constrained('games')
                ->nullOnDelete();
            $table->foreignUlid('current_arbiter_member_id')
                ->nullable()
                ->constrained('multiplayer_lobby_members')
                ->nullOnDelete();
            $table->foreignUlid('current_source_prompt_id')
                ->nullable()
                ->constrained('prompts')
                ->nullOnDelete();
            $table->string('turn_status')->nullable();

            $table->index(['status', 'turn_status']);
        });

        DB::table('multiplayer_lobbies')
            ->where('mode', 'judge')
            ->update(['mode' => 'arbiter']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('multiplayer_lobbies')
            ->where('mode', 'arbiter')
            ->update(['mode' => 'judge']);

        Schema::table('multiplayer_lobbies', function (Blueprint $table): void {
            $table->dropIndex(['status', 'turn_status']);
            $table->dropForeign(['current_source_prompt_id']);
            $table->dropForeign(['current_arbiter_member_id']);
            $table->dropForeign(['game_id']);
            $table->dropColumn([
                'current_source_prompt_id',
                'current_arbiter_member_id',
                'game_id',
                'turn_status',
            ]);
        });
    }
};
