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
        Schema::create('multiplayer_lobbies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('story_id')->constrained()->cascadeOnUpdate();
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnUpdate();
            $table->string('room_code')->unique();
            $table->string('mode');
            $table->string('status')->default('waiting');
            $table->unsignedTinyInteger('max_players')->default(4);
            $table->json('settings');
            $table->timestamps();

            $table->index(['story_id', 'status']);
            $table->index(['host_user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('multiplayer_lobbies');
    }
};
