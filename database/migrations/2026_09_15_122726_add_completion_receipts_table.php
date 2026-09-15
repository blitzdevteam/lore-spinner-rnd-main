<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('completion_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->unsignedSmallInteger('story_cycle_number')->default(1);
            $table->unsignedSmallInteger('session_number');
            $table->string('mode', 16);
            $table->json('receipt');
            $table->timestamps();

            $table->unique(['game_id', 'story_cycle_number', 'session_number', 'mode'], 'completion_receipts_cycle_unique');
            $table->index(['user_id', 'story_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('completion_receipts');
    }
};
