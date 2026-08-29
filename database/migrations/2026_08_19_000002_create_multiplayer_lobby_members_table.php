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
        Schema::create('multiplayer_lobby_members', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('lobby_id')->constrained('multiplayer_lobbies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamp('joined_at');
            $table->timestamps();

            $table->unique(['lobby_id', 'user_id']);
            $table->index(['lobby_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('multiplayer_lobby_members');
    }
};
