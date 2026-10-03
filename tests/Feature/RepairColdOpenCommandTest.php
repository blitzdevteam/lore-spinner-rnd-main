<?php

declare(strict_types=1);

use App\Ai\Agents\Adaptation\ColdOpenRepairAgent;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Enums\Chapter\ChapterStatusEnum;
use App\Models\Story;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Ai\Prompts\AgentPrompt;

uses(DatabaseTransactions::class);

function coldOpenRepairStory(): Story
{
    $story = Story::factory()->create([
        'title' => 'Peter Pan: Wendy\'s Neverland',
    ]);

    $adaptation = $story->adaptation()->create([
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
        'boiler_enabled' => true,
        'story_session_map' => ['protagonist' => 'Wendy Darling'],
        'voice_profile' => [
            'anchor_card' => ['Keep the Barrie aside.'],
            'voice_anchor' => [
                ['mode' => 'aside', 'prose' => 'Which is, of course, no way to mend a shadow at all.'],
            ],
        ],
    ]);

    $adaptation->sessionAdaptations()->create([
        'session_number' => 1,
        'session_status' => SessionAdaptationStatusEnum::COMPLETED,
        'boiler_status' => BoilerStatus::FINALIZED,
        'entry_point_diagnosis' => [
            'cold_open' => 'A boy is crying on the floor. What do you do?',
            'emotional_promise' => 'The emotional promise of this cold open is: transgression.',
            'start_event_position' => 1,
            'situation_stakes_world' => [
                'established' => 'Night in the Darling nursery.',
                'deferred' => 'Fairy dust stays out of the opening.',
            ],
            'first_choice_spec' => [
                'setup_prose' => 'He holds out his hand at the open window.',
                'threshold_stake' => 'Whether Wendy goes.',
                'question' => 'What do you do?',
                'option_1' => 'You go with Peter at once.',
                'option_2' => 'You wake John and Michael first.',
                'option_3_unexpected' => 'You make him prove himself.',
            ],
            'format_specific_cut' => [
                'must_reintroduce' => [
                    [
                        'fact' => 'The shadow will not stick, and soap is the wrong tool.',
                        'marker' => 'COLD_OPEN',
                    ],
                ],
            ],
        ],
    ]);

    $chapter = $story->chapters()->create([
        'position' => 1,
        'title' => 'The nursery',
        'teaser' => 'Night.',
        'content' => 'The nursery window is open.',
        'status' => ChapterStatusEnum::READY_TO_PLAY,
    ]);

    $chapter->events()->create([
        'position' => 1,
        'title' => 'Wendy wakes',
        'content' => 'He tries a cake of soap on the shadow.',
        'session_number' => 1,
    ]);

    return $story;
}

function validRepairedOpening(): string
{
    return 'It is the middle of the night, and this is your nursery in a tall London house. You are Wendy Darling, the eldest, and you are the only one awake. John and Michael sleep on. Peter Pan is on the floor with his shadow come loose, and the cake of soap will not fasten it. He holds out his hand and asks you to come through the open window this very night.';
}

it('prints the pulled Peter Pan facts without calling the model', function (): void {
    ColdOpenRepairAgent::fake()->preventStrayPrompts();

    $story = coldOpenRepairStory();

    $this->artisan('stories:repair-cold-open', [
        'story' => $story->slug,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Wendy Darling')
        ->expectsOutputToContain('The shadow will not stick, and soap is the wrong tool.')
        ->expectsOutputToContain('Choice question kept off the prose: What do you do?')
        ->expectsOutputToContain('The model was not called')
        ->assertSuccessful();

    expect($story->adaptation->sessionAdaptations()->first()->entry_point_diagnosis['cold_open'])
        ->toBe('A boy is crying on the floor. What do you do?');
});

it('stores only a valid cold open and leaves the rest of the diagnosis', function (): void {
    $prose = validRepairedOpening();

    ColdOpenRepairAgent::fake([
        ['cold_open' => $prose],
    ])->preventStrayPrompts();

    $story = coldOpenRepairStory();

    $this->artisan('stories:repair-cold-open', [
        'story' => $story->slug,
        '--apply' => true,
    ])->assertSuccessful();

    $diagnosis = $story->adaptation->sessionAdaptations()->first()->entry_point_diagnosis;

    expect($diagnosis['cold_open'])->toBe($prose)
        ->and($diagnosis['emotional_promise'])->toBe('The emotional promise of this cold open is: transgression.');

    ColdOpenRepairAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'cake of soap')
        && str_contains($prompt->prompt, 'Wendy Darling')
        && str_contains($prompt->prompt, 'COMPLETE CURRENT BOILER ENTRY-POINT DIAGNOSIS'));
});

it('refuses to store an opening that still asks the player a question', function (): void {
    ColdOpenRepairAgent::fake([
        ['cold_open' => validRepairedOpening()."\n\nDo you take his hand?"],
    ])->preventStrayPrompts();

    $story = coldOpenRepairStory();

    $this->artisan('stories:repair-cold-open', [
        'story' => $story->slug,
        '--apply' => true,
    ])->assertFailed();

    expect($story->adaptation->sessionAdaptations()->first()->entry_point_diagnosis['cold_open'])
        ->toBe('A boy is crying on the floor. What do you do?');
});
