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
        Schema::create('discord_activity_rooms', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('instance_id')->unique();
            $table->string('channel_id')->nullable();
            $table->string('guild_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discord_activity_rooms');
    }
};
