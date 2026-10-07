<?php

declare(strict_types=1);

use App\Ai\Adaptation\ColdOpenRepairValidator;
use App\Ai\Adaptation\EntryPointDiagnosisValidator;
use App\Ai\Adaptation\SessionCloseValidator;
use App\Models\Event;
use Illuminate\Support\Collection;

function handoffTestEvents(): Collection
{
    return collect([66, 67, 74])->map(fn (int $position): Event => (new Event())->forceFill(['id' => $position])->setAttribute('story_position', $position));
}

function handoffTestDiagnosis(array $overrides = []): array
{
    return array_replace([
        'start_event_position' => 66,
        'cold_open' => 'Your arms ache with flying. You are Wendy Darling, and the island comes up gold under Peter\'s pointing hand. You turn to John and Michael in the dark.',
        'format_specific_cut' => ['must_reintroduce' => [['fact' => 'Peter cannot stop.', 'marker' => 'LATER']]],
    ], $overrides);
}

function handoffTestClose(array $overrides = []): array
{
    return array_replace_recursive([
        'resolution_prose' => 'The ship sleeps, and you hold Peter tightly.',
        'hook_transition' => 'Long Tom keeps his black watch over the pair of you.',
        'session_end_choice' => [
            'final_line' => 'Long Tom keeps his black watch, and you hold Peter tightly.',
            'option_a' => ['text' => 'You resolve to tell Hook to his face what you think of him.'],
            'option_b' => ['text' => 'You listen for any sign of Peter.'],
            'option_c' => ['text' => 'You put the boys first.'],
        ],
    ], $overrides);
}

it('accepts an entry point that starts in session and keeps the cold open contract', function () {
    (new EntryPointDiagnosisValidator(new ColdOpenRepairValidator()))
        ->validate(handoffTestDiagnosis(), handoffTestEvents(), [67], 'Wendy Darling');

    expect(true)->toBeTrue();
});

it('rejects an entry point that skips a must-breathe event', function () {
    (new EntryPointDiagnosisValidator(new ColdOpenRepairValidator()))
        ->validate(handoffTestDiagnosis(['start_event_position' => 74]), handoffTestEvents(), [67], 'Wendy Darling');
})->throws(RuntimeException::class, 'The start event 74 skips must-breathe events 67');

it('rejects a cold open that ends on a question to the player', function () {
    $diagnosis = handoffTestDiagnosis(['cold_open' => 'Your breath comes oddly. You are Wendy Darling, alone in the dark air. What do you do?']);

    (new EntryPointDiagnosisValidator(new ColdOpenRepairValidator()))
        ->validate($diagnosis, handoffTestEvents(), [], 'Wendy Darling');
})->throws(RuntimeException::class, 'ends on a question to the player');

it('rejects a start position outside the session', function () {
    (new EntryPointDiagnosisValidator(new ColdOpenRepairValidator()))
        ->validate(handoffTestDiagnosis(['start_event_position' => 80]), handoffTestEvents(), [], 'Wendy Darling');
})->throws(RuntimeException::class, 'start_event_position 80 is not one of this session');

it('accepts a close that ends on an image with the player owning every option', function () {
    (new SessionCloseValidator())->validate(handoffTestClose());

    expect(true)->toBeTrue();
});

it('rejects a close whose final line instructs the player', function () {
    (new SessionCloseValidator())->validate(handoffTestClose([
        'session_end_choice' => ['final_line' => 'Choose your promise, for promises grow teeth in the dark.'],
    ]));
})->throws(RuntimeException::class, 'talks to the player about choosing');

it('rejects a session-end option that decides another character', function () {
    (new SessionCloseValidator())->validate(handoffTestClose([
        'session_end_choice' => ['option_a' => ['text' => 'Peter vows to strike for Hook at once.']],
    ]));
})->throws(RuntimeException::class, 'option_a.text is not the player\'s own action');
