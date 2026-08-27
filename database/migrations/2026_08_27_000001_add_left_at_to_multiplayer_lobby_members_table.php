<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('multiplayer_lobby_members', function (Blueprint $table): void {
            $table->timestamp('left_at')->nullable()->after('joined_at');
            $table->index(['lobby_id', 'left_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('multiplayer_lobby_members', function (Blueprint $table): void {
            $table->dropIndex(['lobby_id', 'left_at']);
            $table->dropColumn('left_at');
        });
    }
};
