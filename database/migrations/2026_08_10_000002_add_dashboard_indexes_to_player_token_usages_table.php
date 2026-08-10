<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_token_usages', function (Blueprint $table): void {
            $table->index('occurred_at', 'ptu_occurred_at_index');
            $table->index(['user_id', 'occurred_at'], 'ptu_user_occurred_at_index');
            $table->index(['story_id', 'occurred_at'], 'ptu_story_occurred_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('player_token_usages', function (Blueprint $table): void {
            $table->dropIndex('ptu_occurred_at_index');
            $table->dropIndex('ptu_user_occurred_at_index');
            $table->dropIndex('ptu_story_occurred_at_index');
        });
    }
};
