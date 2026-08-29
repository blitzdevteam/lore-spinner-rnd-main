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
        Schema::table('multiplayer_lobbies', function (Blueprint $table): void {
            $table->timestamp('decision_deadline_at')->nullable()->after('turn_status');
            $table->unsignedInteger('decision_remaining_seconds')->nullable()->after('decision_deadline_at');
            $table->index(['turn_status', 'decision_deadline_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('multiplayer_lobbies', function (Blueprint $table): void {
            $table->dropIndex(['turn_status', 'decision_deadline_at']);
            $table->dropColumn([
                'decision_deadline_at',
                'decision_remaining_seconds',
            ]);
        });
    }
};
