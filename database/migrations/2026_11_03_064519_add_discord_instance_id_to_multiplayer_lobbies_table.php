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
            $table->string('discord_instance_id')->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('multiplayer_lobbies', function (Blueprint $table): void {
            $table->dropUnique(['discord_instance_id']);
            $table->dropColumn('discord_instance_id');
        });
    }
};
