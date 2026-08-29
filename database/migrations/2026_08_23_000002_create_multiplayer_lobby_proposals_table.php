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
        Schema::create('multiplayer_lobby_proposals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('lobby_id')->constrained('multiplayer_lobbies')->cascadeOnDelete();
            $table->foreignUlid('source_prompt_id')->constrained('prompts')->cascadeOnDelete();
            $table->foreignUlid('member_id')->constrained('multiplayer_lobby_members')->cascadeOnDelete();
            $table->text('text');
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            $table->unique(['source_prompt_id', 'member_id']);
            $table->index(['lobby_id', 'source_prompt_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('multiplayer_lobby_proposals');
    }
};
