<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameSessionCompletion;
use App\Models\PlayableMoment;
use App\Models\PlayerTokenUsage;
use App\Services\Runtime\PlayerTokenUsageRecorder;
use App\Services\Runtime\PlayerTokenUsageSummary;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->originalDatabaseConnection = config('database.default');

    config([
        'database.default' => 'player_token_usage_testing',
        'database.connections.player_token_usage_testing' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);
    DB::purge('player_token_usage_testing');
    DB::setDefaultConnection('player_token_usage_testing');
    DB::statement('PRAGMA foreign_keys = ON');

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->timestamps();
    });
    Schema::create('stories', function (Blueprint $table): void {
        $table->id();
        $table->timestamps();
    });
    Schema::create('games', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->unsignedBigInteger('story_id');
        $table->unsignedBigInteger('user_id');
        $table->unsignedInteger('current_story_cycle_number')->default(1);
        $table->unsignedInteger('current_session_number')->default(1);
        $table->string('model');
        $table->timestamps();
    });
    Schema::create('prompts', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->ulid('game_id');
        $table->ulid('source_prompt_id')->nullable();
        $table->unsignedInteger('session_number')->nullable();
        $table->string('moment_key')->nullable();
        $table->text('response');
        $table->json('choices')->nullable();
        $table->text('prompt')->nullable();
        $table->timestamps();
    });
    Schema::create('chapters', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('story_id');
        $table->unsignedInteger('position');
        $table->timestamps();
    });
    Schema::create('events', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('chapter_id');
        $table->unsignedInteger('position');
        $table->unsignedInteger('session_number')->nullable();
        $table->timestamps();
    });
    Schema::create('game_session_completions', function (Blueprint $table): void {
        $table->id();
        $table->ulid('game_id');
        $table->unsignedBigInteger('story_id');
        $table->unsignedBigInteger('user_id');
        $table->unsignedInteger('story_cycle_number');
        $table->unsignedInteger('session_number');
        $table->timestamp('started_at')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
    });
    Schema::create('playable_moments', function (Blueprint $table): void {
        $table->id();
        $table->string('moment_key');
        $table->unsignedBigInteger('anchor_event_id')->nullable();
        $table->json('source_event_ids');
        $table->json('scene_card');
        $table->timestamps();
    });

    $migration = require database_path(
        'migrations/2026_08_10_000001_create_player_token_usages_table.php',
    );
    $migration->up();
});

afterEach(function () {
    DB::purge('player_token_usage_testing');
    config(['database.default' => $this->originalDatabaseConnection]);
    DB::setDefaultConnection($this->originalDatabaseConnection);
});

it('persists one priced runtime record and exposes every hierarchy aggregate', function () {
    $userId = DB::table('users')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
    $storyId = DB::table('stories')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
    $chapterId = DB::table('chapters')->insertGetId([
        'story_id' => $storyId,
        'position' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $firstEventId = DB::table('events')->insertGetId([
        'chapter_id' => $chapterId,
        'position' => 1,
        'session_number' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $secondEventId = DB::table('events')->insertGetId([
        'chapter_id' => $chapterId,
        'position' => 2,
        'session_number' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $moment = PlayableMoment::query()->create([
        'moment_key' => 'S1_M01',
        'anchor_event_id' => $firstEventId,
        'source_event_ids' => [$firstEventId, $secondEventId],
        'scene_card' => [],
    ]);
    $game = Game::query()->create([
        'story_id' => $storyId,
        'user_id' => $userId,
        'current_story_cycle_number' => 2,
        'current_session_number' => 1,
        'model' => 'claude-sonnet-4-6',
    ]);
    $prompt = $game->prompts()->create([
        'session_number' => 1,
        'moment_key' => $moment->moment_key,
        'response' => 'The door opens.',
        'choices' => [],
    ]);
    $session = GameSessionCompletion::query()->create([
        'game_id' => $game->id,
        'story_id' => $storyId,
        'user_id' => $userId,
        'story_cycle_number' => 2,
        'session_number' => 1,
        'started_at' => now(),
    ]);
    $rawUsage = [
        'prompt_tokens' => 100,
        'completion_tokens' => 50,
        'cache_write_input_tokens' => 20,
        'cache_read_input_tokens' => 30,
        'thought_tokens' => 10,
    ];

    $recorder = app(PlayerTokenUsageRecorder::class);
    $usage = $recorder->record(
        $game,
        $prompt,
        $rawUsage,
        PlayerTokenUsage::KIND_TURN,
        $moment,
        CarbonImmutable::parse('2026-08-10T12:00:00Z'),
    );
    $duplicate = $recorder->record(
        $game,
        $prompt,
        $rawUsage,
        PlayerTokenUsage::KIND_TURN,
        $moment,
        CarbonImmutable::parse('2026-08-10T12:00:00Z'),
    );

    expect($duplicate->id)->toBe($usage->id)
        ->and(PlayerTokenUsage::query()->count())->toBe(1)
        ->and($usage->game_session_completion_id)->toBe($session->id)
        ->and($usage->playable_moment_id)->toBe($moment->id)
        ->and($usage->anchor_event_id)->toBe($firstEventId)
        ->and($usage->anchor_chapter_id)->toBe($chapterId)
        ->and($usage->source_event_ids)->toBe([$firstEventId, $secondEventId])
        ->and($usage->source_chapter_ids)->toBe([$chapterId])
        ->and($usage->events)->toHaveCount(2)
        ->and($usage->chapters)->toHaveCount(1)
        ->and($usage->total_tokens)->toBe(200)
        ->and($usage->total_cost_usd)->toBe('0.001179000000');

    $summary = app(PlayerTokenUsageSummary::class);
    foreach ([
        $summary->forGame($game->id),
        $summary->forSession($game->id, 2, 1),
        $summary->forMoment($moment->id),
        $summary->forEvent($secondEventId),
        $summary->forChapter($chapterId),
    ] as $totals) {
        expect($totals['requests'])->toBe(1)
            ->and($totals['input_tokens'])->toBe(100)
            ->and($totals['output_tokens'])->toBe(50)
            ->and($totals['cached_input_tokens'])->toBe(50)
            ->and($totals['total_tokens'])->toBe(200)
            ->and((float) $totals['total_cost_usd'])->toBe(0.001179);
    }

    $promptReference = $prompt->id;
    $prompt->delete();
    $usage->refresh();

    expect($usage->prompt_id)->toBeNull()
        ->and($usage->prompt_reference_id)->toBe($promptReference)
        ->and(PlayerTokenUsage::query()->count())->toBe(1);
});
