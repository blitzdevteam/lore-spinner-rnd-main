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
        Schema::table('multiplayer_lobby_members', function (Blueprint $table): void {
            $table->timestamp('last_seen_at')->nullable()->after('left_at');
            $table->index(['lobby_id', 'left_at', 'last_seen_at']);
        });

        DB::table('multiplayer_lobby_members')
            ->whereNull('left_at')
            ->update(['last_seen_at' => now()]);

        DB::table('multiplayer_lobby_members')
            ->whereNotNull('left_at')
            ->update(['last_seen_at' => DB::raw('left_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('multiplayer_lobby_members', function (Blueprint $table): void {
            $table->dropIndex(['lobby_id', 'left_at', 'last_seen_at']);
            $table->dropColumn('last_seen_at');
        });
    }
};
