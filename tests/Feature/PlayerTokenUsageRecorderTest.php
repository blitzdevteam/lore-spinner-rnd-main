<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameSessionCompletion;
use App\Models\PlayableMoment;
use App\Models\PlayerTokenUsage;
use App\Models\PlayerVoiceUsage;
use App\Services\Runtime\GameplayAllowanceResolver;
use App\Services\Runtime\PlayerGameplayUsageSummary;
use App\Services\Runtime\PlayerTokenUsageInsights;
use App\Services\Runtime\PlayerTokenUsageRecorder;
use App\Services\Runtime\PlayerTokenUsageSummary;
use App\Services\Runtime\PlayerVoiceUsageSummary;
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
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->nullable();
        $table->timestamps();
    });
    Schema::create('stories', function (Blueprint $table): void {
        $table->id();
        $table->string('title')->nullable();
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
        $table->string('title')->nullable();
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

    $dashboardIndexes = require database_path(
        'migrations/2026_08_10_000002_add_dashboard_indexes_to_player_token_usages_table.php',
    );
    $dashboardIndexes->up();

    $promptSnapshots = require database_path(
        'migrations/2026_08_11_000001_add_prompt_snapshots_to_player_token_usages.php',
    );
    $promptSnapshots->up();

    $legacyAttributionCleanup = require database_path(
        'migrations/2026_08_11_000002_remove_legacy_session_event_attribution.php',
    );
    $legacyAttributionCleanup->up();

    $voiceUsage = require database_path(
        'migrations/2026_08_11_000003_create_player_voice_usages_table.php',
    );
    $voiceUsage->up();

    $voiceDuration = require database_path(
        'migrations/2026_08_17_000001_add_audio_duration_to_player_voice_usages_table.php',
    );
    $voiceDuration->up();
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
        systemPrompt: 'You are the runtime narrator for this exact moment.',
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
        ->and($usage->system_prompt)->toBe('You are the runtime narrator for this exact moment.')
        ->and($usage->generated_output)->toBe('The door opens.')
        ->and($usage->events)->toHaveCount(2)
        ->and($usage->chapters)->toHaveCount(1)
        ->and($usage->total_tokens)->toBe(200)
        ->and($usage->total_cost_usd)->toBe('0.001179000000');

    $summary = app(PlayerTokenUsageSummary::class);
    foreach ([
        $summary->forGame($game->id),
        $summary->forStoryCycle($game->id, 2),
        $summary->forSession($game->id, 2, 1),
        $summary->forMoment($moment->id),
        $summary->forEvent($secondEventId),
        $summary->forChapter($chapterId),
    ] as $totals) {
        expect($totals['requests'])->toBe(1)
            ->and($totals['input_tokens'])->toBe(100)
            ->and($totals['total_input_tokens'])->toBe(150)
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

it('summarizes voice usage for the active story cycle only', function () {
    $userId = DB::table('users')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
    $storyId = DB::table('stories')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
    $game = Game::query()->create([
        'story_id' => $storyId,
        'user_id' => $userId,
        'current_story_cycle_number' => 2,
        'current_session_number' => 1,
        'model' => 'claude-sonnet-4-6',
    ]);
    $currentPrompt = $game->prompts()->create([
        'session_number' => 1,
        'response' => 'Current narration.',
        'choices' => [],
    ]);
    $previousPrompt = $game->prompts()->create([
        'session_number' => 1,
        'response' => 'Previous narration.',
        'choices' => [],
    ]);

    foreach ([
        ['prompt' => $currentPrompt, 'cycle' => 2, 'characters' => 240, 'duration_ms' => 18_000],
        ['prompt' => $previousPrompt, 'cycle' => 1, 'characters' => 960, 'duration_ms' => 72_000],
    ] as $usage) {
        PlayerVoiceUsage::query()->create([
            'game_id' => $game->id,
            'prompt_id' => $usage['prompt']->id,
            'prompt_reference_id' => $usage['prompt']->id,
            'story_id' => $storyId,
            'user_id' => $userId,
            'story_cycle_number' => $usage['cycle'],
            'session_number' => 1,
            'provider' => 'elevenlabs',
            'model' => 'eleven_v3',
            'voice_id' => 'voice-1',
            'output_format' => 'mp3',
            'billing_unit' => 'characters',
            'input_characters' => $usage['characters'],
            'billable_units' => $usage['characters'],
            'audio_bytes' => 1_024,
            'audio_duration_ms' => $usage['duration_ms'],
            'latency_ms' => 250,
            'currency' => 'USD',
            'pricing_available' => true,
            'pricing_version' => 'test',
            'pricing_effective_from' => now(),
            'pricing_source' => 'test',
            'rate_per_thousand_usd' => '0.100000',
            'estimated_cost_usd' => '0.024000000000',
            'text_snapshot' => $usage['prompt']->response,
            'provider_metadata' => [],
            'occurred_at' => now(),
        ]);
    }

    $summary = app(PlayerVoiceUsageSummary::class);

    expect($summary->forGame($game->id))
        ->toMatchArray([
            'requests' => 2,
            'input_characters' => 1_200,
            'billable_units' => 1_200,
            'audio_duration_ms' => 90_000,
        ])
        ->and($summary->forStoryCycle($game->id, 2))
        ->toMatchArray([
            'requests' => 1,
            'input_characters' => 240,
            'billable_units' => 240,
            'audio_duration_ms' => 18_000,
        ]);
});

it('summarizes player-facing turns and generated voice duration across the account', function () {
    config([
        'gameplay-usage.default_plan.turns' => 250,
        'gameplay-usage.default_plan.voice_minutes' => 60,
    ]);

    $userId = DB::table('users')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
    $storyId = DB::table('stories')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
    $firstGame = Game::query()->create([
        'story_id' => $storyId,
        'user_id' => $userId,
        'current_story_cycle_number' => 1,
        'current_session_number' => 1,
        'model' => 'claude-sonnet-4-6',
    ]);
    $secondGame = Game::query()->create([
        'story_id' => $storyId,
        'user_id' => $userId,
        'current_story_cycle_number' => 1,
        'current_session_number' => 1,
        'model' => 'claude-sonnet-4-6',
    ]);

    $openingPrompt = $firstGame->prompts()->create([
        'session_number' => 1,
        'response' => 'Opening narration.',
        'choices' => [],
    ]);
    $firstTurnPrompt = $firstGame->prompts()->create([
        'session_number' => 1,
        'response' => 'First player turn.',
        'choices' => [],
    ]);
    $secondTurnPrompt = $secondGame->prompts()->create([
        'session_number' => 1,
        'response' => 'Second player turn.',
        'choices' => [],
    ]);

    $tokenUsage = app(PlayerTokenUsageRecorder::class);
    $tokenUsage->record(
        game: $firstGame,
        prompt: $openingPrompt,
        rawUsage: ['prompt_tokens' => 100, 'completion_tokens' => 25],
        kind: PlayerTokenUsage::KIND_BEGIN,
    );
    $tokenUsage->record(
        game: $firstGame,
        prompt: $firstTurnPrompt,
        rawUsage: ['prompt_tokens' => 100, 'completion_tokens' => 25],
        kind: PlayerTokenUsage::KIND_TURN,
    );
    $tokenUsage->record(
        game: $secondGame,
        prompt: $secondTurnPrompt,
        rawUsage: ['prompt_tokens' => 100, 'completion_tokens' => 25],
        kind: PlayerTokenUsage::KIND_TURN,
    );

    foreach ([
        ['game' => $firstGame, 'prompt' => $openingPrompt, 'duration_ms' => 30_000],
        ['game' => $firstGame, 'prompt' => $firstTurnPrompt, 'duration_ms' => 45_000],
        ['game' => $secondGame, 'prompt' => $secondTurnPrompt, 'duration_ms' => 90_000],
    ] as $voice) {
        PlayerVoiceUsage::query()->create([
            'game_id' => $voice['game']->id,
            'prompt_id' => $voice['prompt']->id,
            'prompt_reference_id' => $voice['prompt']->id,
            'story_id' => $storyId,
            'user_id' => $userId,
            'story_cycle_number' => 1,
            'session_number' => 1,
            'provider' => 'elevenlabs',
            'model' => 'eleven_v3',
            'voice_id' => 'voice-1',
            'output_format' => 'mp3',
            'billing_unit' => 'characters',
            'input_characters' => 100,
            'billable_units' => 100,
            'audio_bytes' => 1_024,
            'audio_duration_ms' => $voice['duration_ms'],
            'latency_ms' => 250,
            'currency' => 'USD',
            'pricing_available' => true,
            'pricing_version' => 'test',
            'pricing_effective_from' => now(),
            'pricing_source' => 'test',
            'rate_per_thousand_usd' => '0.100000',
            'estimated_cost_usd' => '0.010000000000',
            'text_snapshot' => $voice['prompt']->response,
            'provider_metadata' => [],
            'occurred_at' => now(),
        ]);
    }

    $summary = app(PlayerGameplayUsageSummary::class);
    $allowance = app(GameplayAllowanceResolver::class)->forUser($userId);

    expect($summary->forGame($firstGame->id))
        ->toBe([
            'turns' => 1,
            'voice_duration_ms' => 75_000,
        ])
        ->and($summary->forUser($userId))
        ->toBe([
            'turns' => 2,
            'voice_duration_ms' => 165_000,
        ])
        ->and($allowance)
        ->toBe([
            'turns' => 250,
            'voice_duration_ms' => 3_600_000,
        ]);
});

it('keeps legacy usage at session scope and removes historical inferred event links', function () {
    $userId = DB::table('users')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
    $storyId = DB::table('stories')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
    $chapterId = DB::table('chapters')->insertGetId([
        'story_id' => $storyId,
        'position' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $eventId = DB::table('events')->insertGetId([
        'chapter_id' => $chapterId,
        'position' => 1,
        'session_number' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $game = Game::query()->create([
        'story_id' => $storyId,
        'user_id' => $userId,
        'current_story_cycle_number' => 1,
        'current_session_number' => 1,
        'model' => 'claude-sonnet-4-6',
    ]);
    $prompt = $game->prompts()->create([
        'session_number' => 1,
        'response' => 'Legacy narration.',
        'choices' => [],
    ]);

    $usage = app(PlayerTokenUsageRecorder::class)->record(
        game: $game,
        prompt: $prompt,
        rawUsage: ['prompt_tokens' => 100, 'completion_tokens' => 25],
        kind: PlayerTokenUsage::KIND_TURN,
        systemPrompt: 'Legacy runtime prompt.',
    );

    $summary = app(PlayerTokenUsageSummary::class);

    expect($usage->playable_moment_id)->toBeNull()
        ->and($usage->anchor_event_id)->toBeNull()
        ->and($usage->anchor_chapter_id)->toBeNull()
        ->and($usage->source_event_ids)->toBe([])
        ->and($usage->source_chapter_ids)->toBe([])
        ->and($usage->events)->toBeEmpty()
        ->and($usage->chapters)->toBeEmpty()
        ->and($summary->forGame($game->id)['requests'])->toBe(1)
        ->and($summary->forSession($game->id, 1, 1)['requests'])->toBe(1)
        ->and($summary->forEvent($eventId)['requests'])->toBe(0)
        ->and($summary->forChapter($chapterId)['requests'])->toBe(0);

    // Recreate the historical bug, then verify the data migration removes it.
    $usage->forceFill([
        'anchor_event_id' => $eventId,
        'anchor_chapter_id' => $chapterId,
        'source_event_ids' => [$eventId],
        'source_chapter_ids' => [$chapterId],
    ])->save();
    $usage->events()->sync([$eventId => ['attribution_role' => 'session_source']]);
    $usage->chapters()->sync([$chapterId => ['attribution_role' => 'session_source']]);

    $cleanup = require database_path(
        'migrations/2026_08_11_000002_remove_legacy_session_event_attribution.php',
    );
    $cleanup->up();
    $usage->refresh()->load(['events', 'chapters']);

    expect($usage->anchor_event_id)->toBeNull()
        ->and($usage->anchor_chapter_id)->toBeNull()
        ->and($usage->source_event_ids)->toBe([])
        ->and($usage->source_chapter_ids)->toBe([])
        ->and($usage->events)->toBeEmpty()
        ->and($usage->chapters)->toBeEmpty();
});

it('builds filterable game player session chapter and daily dashboard insights', function () {
    $firstUserId = DB::table('users')->insertGetId([
        'first_name' => 'Ava',
        'last_name' => 'Stone',
        'email' => 'ava@example.test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $secondUserId = DB::table('users')->insertGetId([
        'first_name' => 'Noah',
        'last_name' => 'Vale',
        'email' => 'noah@example.test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $firstStoryId = DB::table('stories')->insertGetId([
        'title' => 'The Glass Harbor',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $secondStoryId = DB::table('stories')->insertGetId([
        'title' => 'Ashes of Dawn',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $chapterIds = [
        DB::table('chapters')->insertGetId([
            'story_id' => $firstStoryId,
            'position' => 1,
            'title' => 'The Arrival',
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        DB::table('chapters')->insertGetId([
            'story_id' => $firstStoryId,
            'position' => 2,
            'title' => 'Below the Tide',
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        DB::table('chapters')->insertGetId([
            'story_id' => $secondStoryId,
            'position' => 1,
            'title' => 'Embers',
            'created_at' => now(),
            'updated_at' => now(),
        ]),
    ];

    $eventIds = [];
    foreach ($chapterIds as $index => $chapterId) {
        $eventIds[] = DB::table('events')->insertGetId([
            'chapter_id' => $chapterId,
            'position' => 1,
            'session_number' => $index === 1 ? 2 : 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $firstGame = Game::query()->create([
        'story_id' => $firstStoryId,
        'user_id' => $firstUserId,
        'current_story_cycle_number' => 1,
        'current_session_number' => 2,
        'model' => 'claude-sonnet-4-6',
    ]);
    $secondGame = Game::query()->create([
        'story_id' => $secondStoryId,
        'user_id' => $secondUserId,
        'current_story_cycle_number' => 1,
        'current_session_number' => 1,
        'model' => 'claude-sonnet-4-6',
    ]);

    $requests = [
        [
            'game' => $firstGame,
            'event_id' => $eventIds[0],
            'moment_key' => 'S1_M01',
            'session' => 1,
            'usage' => [
                'prompt_tokens' => 100,
                'completion_tokens' => 20,
                'cache_read_input_tokens' => 10,
            ],
            'occurred_at' => '2026-08-08T12:00:00Z',
        ],
        [
            'game' => $firstGame,
            'event_id' => $eventIds[1],
            'moment_key' => 'S2_M01',
            'session' => 2,
            'usage' => [
                'prompt_tokens' => 400,
                'completion_tokens' => 100,
                'cache_write_input_tokens' => 50,
            ],
            'occurred_at' => '2026-08-10T10:00:00Z',
        ],
        [
            'game' => $secondGame,
            'event_id' => $eventIds[2],
            'moment_key' => 'S1_M02',
            'session' => 1,
            'usage' => [
                'prompt_tokens' => 200,
                'completion_tokens' => 50,
            ],
            'occurred_at' => '2026-08-10T14:00:00Z',
        ],
    ];

    $recorder = app(PlayerTokenUsageRecorder::class);
    foreach ($requests as $request) {
        $moment = PlayableMoment::query()->create([
            'moment_key' => $request['moment_key'],
            'anchor_event_id' => $request['event_id'],
            'source_event_ids' => [$request['event_id']],
            'scene_card' => [],
        ]);
        $prompt = $request['game']->prompts()->create([
            'session_number' => $request['session'],
            'moment_key' => $request['moment_key'],
            'response' => 'Narration',
            'choices' => [],
        ]);

        $recorder->record(
            $request['game'],
            $prompt,
            $request['usage'],
            PlayerTokenUsage::KIND_TURN,
            $moment,
            CarbonImmutable::parse($request['occurred_at']),
        );
    }

    $insights = app(PlayerTokenUsageInsights::class);
    $overview = $insights->overview(PlayerTokenUsage::query());
    $daily = $insights->dailyTokenMix(PlayerTokenUsage::query());

    expect($overview['requests'])->toBe(3)
        ->and($overview['input_tokens'])->toBe(700)
        ->and($overview['cached_input_tokens'])->toBe(60)
        ->and($overview['output_tokens'])->toBe(170)
        ->and($overview['total_tokens'])->toBe(930)
        ->and(round($overview['cache_share'], 1))->toBe(7.9)
        ->and($overview['top_session']['total_tokens'])->toBe(550)
        ->and($daily['labels'])->toBe(['Aug 8, 2026', 'Aug 10, 2026'])
        ->and($daily['input_tokens'])->toBe([100, 600])
        ->and($daily['cached_input_tokens'])->toBe([10, 50])
        ->and($daily['output_tokens'])->toBe([20, 150]);

    expect($insights->topConsumers(PlayerTokenUsage::query(), 'game')[0])
        ->toMatchArray(['key' => $firstGame->id, 'total_tokens' => 680])
        ->and($insights->topConsumers(PlayerTokenUsage::query(), 'player')[0])
        ->toMatchArray(['key' => (string) $firstUserId, 'total_tokens' => 680])
        ->and($insights->topConsumers(PlayerTokenUsage::query(), 'session')[0])
        ->toMatchArray(['key' => $firstGame->id, 'total_tokens' => 550])
        ->and($insights->topConsumers(
            PlayerTokenUsage::query()->where('game_id', $firstGame->id),
            'chapter',
        )[0])
        ->toMatchArray(['key' => (string) $chapterIds[1], 'total_tokens' => 550]);
});
