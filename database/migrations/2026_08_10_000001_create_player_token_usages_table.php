<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_token_usages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignUlid('prompt_id')->nullable()->constrained('prompts')->nullOnDelete();
            $table->string('prompt_reference_id', 26)->unique('ptu_prompt_reference_unique');
            $table->string('source_prompt_reference_id', 26)->nullable();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('game_session_completion_id')
                ->nullable()
                ->constrained('game_session_completions')
                ->nullOnDelete();
            $table->foreignId('playable_moment_id')
                ->nullable()
                ->constrained('playable_moments')
                ->nullOnDelete();
            $table->foreignId('anchor_event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('anchor_chapter_id')->nullable()->constrained('chapters')->nullOnDelete();
            $table->unsignedInteger('story_cycle_number');
            $table->unsignedInteger('session_number');
            $table->string('moment_key')->nullable();
            $table->json('source_event_ids');
            $table->json('source_chapter_ids');
            $table->string('kind', 32);

            $table->string('provider', 32);
            $table->string('model', 128);
            $table->char('currency', 3)->default('USD');
            $table->string('pricing_version', 96);
            $table->timestampTz('pricing_effective_from')->nullable();
            $table->text('pricing_source');

            $table->decimal('input_price_per_million_usd', 16, 6);
            $table->decimal('output_price_per_million_usd', 16, 6);
            $table->decimal('cache_write_price_per_million_usd', 16, 6);
            $table->decimal('cache_read_price_per_million_usd', 16, 6);

            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cache_write_input_tokens')->default(0);
            $table->unsignedBigInteger('cache_read_input_tokens')->default(0);
            $table->unsignedBigInteger('cached_input_tokens')->default(0);
            $table->unsignedBigInteger('total_input_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->unsignedBigInteger('reasoning_tokens')->default(0);

            $table->decimal('input_cost_usd', 20, 12);
            $table->decimal('output_cost_usd', 20, 12);
            $table->decimal('cache_write_cost_usd', 20, 12);
            $table->decimal('cache_read_cost_usd', 20, 12);
            $table->decimal('total_cost_usd', 20, 12);
            $table->boolean('usage_reported')->default(true);
            $table->json('raw_usage');
            $table->timestampTz('occurred_at');
            $table->timestamps();

            $table->index(
                ['game_id', 'story_cycle_number', 'session_number'],
                'ptu_game_session_index',
            );
            $table->index(['playable_moment_id', 'occurred_at'], 'ptu_moment_index');
            $table->index(['anchor_event_id', 'occurred_at'], 'ptu_anchor_event_index');
            $table->index(['anchor_chapter_id', 'occurred_at'], 'ptu_anchor_chapter_index');
            $table->index(['provider', 'model', 'occurred_at'], 'ptu_model_index');
        });

        Schema::create('player_token_usage_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('player_token_usage_id')
                ->constrained('player_token_usages')
                ->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('attribution_role', 24);

            $table->unique(['player_token_usage_id', 'event_id'], 'ptu_event_unique');
            $table->index(['event_id', 'player_token_usage_id'], 'ptu_event_lookup');
        });

        Schema::create('player_token_usage_chapters', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('player_token_usage_id')
                ->constrained('player_token_usages')
                ->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained('chapters')->cascadeOnDelete();
            $table->string('attribution_role', 24);

            $table->unique(['player_token_usage_id', 'chapter_id'], 'ptu_chapter_unique');
            $table->index(['chapter_id', 'player_token_usage_id'], 'ptu_chapter_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_token_usage_chapters');
        Schema::dropIfExists('player_token_usage_events');
        Schema::dropIfExists('player_token_usages');
    }
};
