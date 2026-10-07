<?php

declare(strict_types=1);

use App\Ai\Adaptation\SessionHandoff;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Jobs\Adaptation\BoilerDraftJob;
use App\Jobs\Adaptation\EntryPointDiagnosisJob;
use App\Jobs\Adaptation\SessionCloseJob;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\StoryAdaptation;
use App\Support\Adaptation\SessionAdaptationChain;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;

uses(DatabaseTransactions::class);

/**
 * @return array{0: Story, 1: list<SessionAdaptation>}
 */
function chainTestStory(): array
{
    $story = Story::factory()->create();
    $adaptation = StoryAdaptation::create([
        'story_id' => $story->id,
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
        'boiler_enabled' => true,
    ]);
    $sessions = array_map(fn (int $number): SessionAdaptation => SessionAdaptation::create([
        'story_adaptation_id' => $adaptation->id,
        'session_number' => $number,
        'session_status' => SessionAdaptationStatusEnum::PENDING,
    ]), [1, 2, 3]);

    return [$story->fresh(), $sessions];
}

it('runs every session entry point first, in one batch, before any session chain', function () {
    Bus::fake();
    [$story] = chainTestStory();

    SessionAdaptationChain::dispatchBatch($story, [1, 2, 3]);

    Bus::assertBatchCount(1);
    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 3
        && $batch->jobs->every(fn ($job): bool => $job instanceof EntryPointDiagnosisJob));
});

it('keeps the entry point at the head of a single-session rerun chain', function () {
    [$story] = chainTestStory();

    $jobs = collect(SessionAdaptationChain::jobsForSession($story, 2));

    expect($jobs->first())->toBeInstanceOf(EntryPointDiagnosisJob::class)
        ->and($jobs->get(1))->toBeInstanceOf(BoilerDraftJob::class)
        ->and($jobs->filter(fn ($job): bool => $job instanceof EntryPointDiagnosisJob))->toHaveCount(1)
        ->and($jobs->contains(fn ($job): bool => $job instanceof SessionCloseJob))->toBeTrue();
});

it('hands a session its next session opening and knows the final session', function () {
    [, $sessions] = chainTestStory();
    $sessions[1]->update(['entry_point_diagnosis' => [
        'cold_open' => 'You are Wendy Darling, and the island comes up gold.',
        'start_event_position' => 66,
        'situation_stakes_world' => ['established' => 'First sight of the island.'],
    ]]);

    expect(SessionHandoff::nextSessionOpening($sessions[0]))->toMatchArray([
        'session_number' => 2,
        'cold_open' => 'You are Wendy Darling, and the island comes up gold.',
        'start_event_position' => 66,
    ])
        ->and(SessionHandoff::nextSessionOpening($sessions[1]))->toBeNull()
        ->and(SessionHandoff::isFinalSession($sessions[1]))->toBeFalse()
        ->and(SessionHandoff::isFinalSession($sessions[2]))->toBeTrue();
});
