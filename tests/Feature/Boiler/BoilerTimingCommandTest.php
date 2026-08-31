<?php

declare(strict_types=1);

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\StoryAdaptation;
use App\Support\Adaptation\BoilerTimingReport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;

uses(DatabaseTransactions::class);

it('formats boil durations for humans', function () {
    expect(BoilerTimingReport::formatDuration(45))->toBe('45s')
        ->and(BoilerTimingReport::formatDuration(125))->toBe('2m 5s')
        ->and(BoilerTimingReport::formatDuration(3661))->toBe('1h 1m');
});

it('summarizes full and post-draft-only session boil timing', function () {
    $story = Story::factory()->create(['title' => 'Pride and Prejudice', 'slug' => 'pride-and-prejudice']);
    $adaptation = StoryAdaptation::create([
        'story_id' => $story->id,
        'adaptation_status' => AdaptationStatusEnum::PARTIAL_COMPLETION,
        'boiler_enabled' => true,
    ]);

    SessionAdaptation::create([
        'story_adaptation_id' => $adaptation->id,
        'session_number' => 1,
        'session_status' => SessionAdaptationStatusEnum::COMPLETED,
        'boiler_status' => BoilerStatus::FINALIZED,
        'boiler_drafting_started_at' => Carbon::parse('2026-08-30 16:35:27'),
        'boiler_generated_at' => Carbon::parse('2026-08-30 16:40:06'),
        'boiler_finalized_at' => Carbon::parse('2026-08-30 16:50:00'),
        'runtime_narrator_assembled_at' => Carbon::parse('2026-08-30 16:51:00'),
        'runtime_narrator_prompt' => str_repeat('a', 350_000),
    ]);

    SessionAdaptation::create([
        'story_adaptation_id' => $adaptation->id,
        'session_number' => 2,
        'session_status' => SessionAdaptationStatusEnum::COMPLETED,
        'boiler_status' => BoilerStatus::FINALIZED,
        'boiler_generated_at' => Carbon::parse('2026-08-30 17:00:00'),
        'boiler_finalized_at' => Carbon::parse('2026-08-30 17:08:00'),
        'runtime_narrator_assembled_at' => Carbon::parse('2026-08-30 17:09:00'),
    ]);

    $report = (new BoilerTimingReport)->forAdaptation($adaptation->load(['story', 'sessionAdaptations.playableCut']));

    expect($report['summary']['completed_session_count'])->toBe(2)
        ->and($report['summary']['full_track_session_count'])->toBe(1)
        ->and($report['summary']['post_draft_only_session_count'])->toBe(1)
        ->and($report['summary']['boil_wall_clock_human'])->toBe('33m 33s')
        ->and($report['sessions'][0]['coverage'])->toBe('full')
        ->and($report['sessions'][0]['draft_human'])->toBe('4m 39s')
        ->and($report['sessions'][1]['coverage'])->toBe('post-draft-only')
        ->and($report['sessions'][1]['draft_human'])->toBeNull();
});

it('reports boiler timing for all boiler-enabled titles', function () {
    $story = Story::factory()->create(['slug' => 'speckled-band']);
    StoryAdaptation::create([
        'story_id' => $story->id,
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
        'boiler_enabled' => true,
    ]);

    $this->artisan('stories:boiler-timing')
        ->assertSuccessful()
        ->expectsOutputToContain('speckled-band');
});

it('reports boiler timing for one story as json', function () {
    $story = Story::factory()->create(['slug' => 'treasure-island']);
    StoryAdaptation::create([
        'story_id' => $story->id,
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
        'boiler_enabled' => true,
    ]);

    $this->artisan('stories:boiler-timing', ['story' => 'treasure-island', '--json' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('"story_slug": "treasure-island"');
});
