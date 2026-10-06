<?php

declare(strict_types=1);

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Jobs\Adaptation\BoilerFinalizeJob;
use App\Jobs\Adaptation\EditorialVerificationJob;
use App\Jobs\Adaptation\StoryRepairJob;
use App\Models\Story;
use App\Models\StoryAdaptation;
use App\Support\Adaptation\SessionAdaptationChain;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;

uses(DatabaseTransactions::class);

function boilerStory(): Story
{
    $story = Story::factory()->create();
    StoryAdaptation::create([
        'story_id' => $story->id,
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
        'boiler_enabled' => true,
    ]);

    return $story->fresh();
}

it('repairs a single-session rerun right after finalize, before verification', function () {
    $classes = array_map(
        static fn (object $job): string => $job::class,
        SessionAdaptationChain::jobsForSession(boilerStory(), 2),
    );
    $finalize = array_search(BoilerFinalizeJob::class, $classes, true);

    expect($classes[$finalize + 1])->toBe(StoryRepairJob::class)
        ->and($classes[$finalize + 2])->toBe(EditorialVerificationJob::class);
});

it('leaves repair out of parallel session chains', function () {
    $classes = array_map(
        static fn (object $job): string => $job::class,
        SessionAdaptationChain::jobsForSession(boilerStory(), 2, withStoryRepair: false),
    );

    expect($classes)->not->toContain(StoryRepairJob::class);
});

it('runs the post-batch repair pass in story order', function () {
    Bus::fake();
    $story = boilerStory();

    SessionAdaptationChain::dispatchStoryRepairPass($story->id, [3, 1, 2]);

    Bus::assertChained([StoryRepairJob::class, StoryRepairJob::class, StoryRepairJob::class]);
});
