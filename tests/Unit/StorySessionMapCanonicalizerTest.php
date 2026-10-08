<?php

declare(strict_types=1);

use App\Jobs\Adaptation\StorySessionMapJob;
use App\Models\Event;

it('moves a must-breathe event to the session that owns it, drops unknown ids, clamps counts and reconciles minutes', function () {
    $events = collect(range(1, 6))->map(fn (int $position): Event => (new Event)->forceFill(['id' => 100 + $position])->setAttribute('story_position', $position));

    $map = StorySessionMapJob::canonicalizeBoilingProfile([
        'session_allocation' => [
            ['session_number' => 1, 'event_range' => '1-3'],
            ['session_number' => 2, 'event_range' => '4-6'],
        ],
        'boiling_profile' => [
            'target_total_playtime_minutes' => 99,
            'per_session_guidance' => [
                ['session_number' => 1, 'target_playtime_minutes' => 20, 'recommended_moment_count_min' => 2, 'recommended_moment_count_max' => 5, 'must_breathe_event_ids' => [101], 'must_compress_event_ids' => []],
                ['session_number' => 2, 'target_playtime_minutes' => 25, 'recommended_moment_count_min' => 2, 'recommended_moment_count_max' => 3, 'must_breathe_event_ids' => [102, 105, 999], 'must_compress_event_ids' => ['106']],
            ],
        ],
    ], $events);

    $guidance = $map['boiling_profile']['per_session_guidance'];

    expect($guidance[0]['must_breathe_event_ids'])->toBe([101, 102])
        ->and($guidance[1]['must_breathe_event_ids'])->toBe([105])
        ->and($guidance[1]['must_compress_event_ids'])->toBe([106])
        ->and($guidance[0]['recommended_moment_count_max'])->toBe(3)
        ->and($guidance[0]['recommended_moment_count_min'])->toBe(2)
        ->and($map['boiling_profile']['target_total_playtime_minutes'])->toBe(45);
});

it('leaves a sound plan unchanged', function () {
    $events = collect(range(1, 4))->map(fn (int $position): Event => (new Event)->forceFill(['id' => $position])->setAttribute('story_position', $position));
    $map = [
        'session_allocation' => [['session_number' => 1, 'event_range' => '1-4']],
        'boiling_profile' => [
            'target_total_playtime_minutes' => 20,
            'per_session_guidance' => [['session_number' => 1, 'target_playtime_minutes' => 20, 'recommended_moment_count_min' => 2, 'recommended_moment_count_max' => 4, 'must_breathe_event_ids' => [2], 'must_compress_event_ids' => [3]]],
        ],
    ];

    expect(StorySessionMapJob::canonicalizeBoilingProfile($map, $events))->toBe($map);
});
