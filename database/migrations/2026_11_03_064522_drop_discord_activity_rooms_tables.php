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
        Schema::dropIfExists('discord_activity_room_members');
        Schema::dropIfExists('discord_activity_rooms');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('discord_activity_rooms', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('instance_id')->unique();
            $table->string('channel_id')->nullable();
            $table->string('guild_id')->nullable();
            $table->timestamps();
        });

        Schema::create('discord_activity_room_members', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('room_id')->constrained('discord_activity_rooms')->cascadeOnDelete();
            $table->string('discord_user_id');
            $table->string('username');
            $table->string('global_name')->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['room_id', 'discord_user_id']);
        });
    }
};
