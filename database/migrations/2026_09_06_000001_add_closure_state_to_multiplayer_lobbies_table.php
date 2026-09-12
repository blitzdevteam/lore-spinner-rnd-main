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
            $table->timestamp('last_activity_at')->nullable()->after('settings');
            $table->timestamp('closed_at')->nullable()->after('last_activity_at');
            $table->string('closed_reason')->nullable()->after('closed_at');
            $table->string('closed_status')->nullable()->after('closed_reason');

            $table->index(['status', 'last_activity_at']);
        });

        DB::table('multiplayer_lobbies')
            ->whereNull('last_activity_at')
            ->update(['last_activity_at' => DB::raw('updated_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('multiplayer_lobbies', function (Blueprint $table): void {
            $table->dropIndex(['status', 'last_activity_at']);
            $table->dropColumn([
                'last_activity_at',
                'closed_at',
                'closed_reason',
                'closed_status',
            ]);
        });
    }
};
