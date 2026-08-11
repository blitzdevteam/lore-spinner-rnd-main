<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_voice_usages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignUlid('prompt_id')->nullable()->constrained('prompts')->nullOnDelete();
            $table->string('prompt_reference_id', 26)->index();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('story_cycle_number');
            $table->unsignedInteger('session_number');

            $table->string('provider', 32);
            $table->string('model', 128);
            $table->string('voice_id', 128)->nullable();
            $table->string('output_format', 64);
            $table->string('billing_unit', 32);
            $table->unsignedBigInteger('input_characters');
            $table->unsignedBigInteger('billable_units');
            $table->unsignedBigInteger('audio_bytes');
            $table->unsignedInteger('latency_ms');

            $table->char('currency', 3)->default('USD');
            $table->boolean('pricing_available')->default(true);
            $table->string('pricing_version', 96)->nullable();
            $table->timestampTz('pricing_effective_from')->nullable();
            $table->text('pricing_source')->nullable();
            $table->decimal('rate_per_thousand_usd', 16, 6)->nullable();
            $table->decimal('estimated_cost_usd', 20, 12)->nullable();

            $table->string('provider_request_id', 160)->nullable()->index();
            $table->string('provider_trace_id', 160)->nullable();
            $table->longText('text_snapshot');
            $table->json('provider_metadata');
            $table->timestampTz('occurred_at');
            $table->timestamps();

            $table->index(
                ['game_id', 'story_cycle_number', 'session_number'],
                'pvu_game_session_index',
            );
            $table->index(['provider', 'model', 'occurred_at'], 'pvu_provider_model_index');
            $table->index(['story_id', 'occurred_at'], 'pvu_story_index');
            $table->index(['user_id', 'occurred_at'], 'pvu_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_voice_usages');
    }
};
