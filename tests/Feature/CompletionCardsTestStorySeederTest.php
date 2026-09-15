<?php

declare(strict_types=1);

use App\Http\Controllers\User\GameController;
use App\Models\Game;
use App\Models\Prompt;
use App\Models\Story;
use App\Models\User;
use App\Services\ChaosEngineService;
use App\Services\Runtime\CompletionReceiptBuilder;
use App\Services\Runtime\PlayableMomentResolver;
use Database\Seeders\CompletionCardsTestStorySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CompletionReceiptFixtures;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(CompletionCardsTestStorySeeder::class);
});

it('seeds a published three-session boiler story that is launch-playable', function (): void {
    $story = Story::query()->where('slug', CompletionCardsTestStorySeeder::SLUG)->first();

    expect($story)->not->toBeNull()
        ->and($story->title)->toBe(CompletionCardsTestStorySeeder::TITLE)
        ->and($story->status->value)->toBe('published')
        ->and($story->adaptation)->not->toBeNull()
        ->and($story->adaptation->boiler_enabled)->toBeTrue()
        ->and($story->adaptation->sessionAdaptations)->toHaveCount(3)
        ->and(GameController::LAUNCH_SLUGS)->toContain(CompletionCardsTestStorySeeder::SLUG);
});

it('authors three playable moments, a session-end close, and story-native identity labels', function (): void {
    $story = Story::query()
        ->where('slug', CompletionCardsTestStorySeeder::SLUG)
        ->with(['adaptation.sessionAdaptations.playableCut.moments'])
        ->firstOrFail();

    $labels = collect($story->adaptation->story_session_map['alignment_labels'] ?? [])
        ->pluck('label')
        ->all();

    expect($labels)->toContain('The one who listens before walking away.')
        ->and($labels)->not->toContain('chaotic')
        ->and($labels)->not->toContain('lawful')
        ->and($labels)->not->toContain('neutral');

    foreach ($story->adaptation->sessionAdaptations as $session) {
        expect($session->runtime_narrator_prompt)->not->toBeNull()
            ->and($session->runtime_narrator_prompt)->toContain('[SESSION_CLOSE_INJECTION_POINT]')
            ->and($session->runtime_narrator_prompt)->toContain('[ACTIVE_PLAYABLE_MOMENT_INJECTION_POINT]')
            ->and($session->playableCut)->not->toBeNull()
            ->and($session->playableCut->moments)->toHaveCount(3)
            ->and($session->session_close_design['session_end_choice']['final_line'] ?? null)->not->toBeEmpty();
    }

    $sessionOne = $story->adaptation->sessionAdaptations->firstWhere('session_number', 1);
    $sessionTwo = $story->adaptation->sessionAdaptations->firstWhere('session_number', 2);
    $sessionThree = $story->adaptation->sessionAdaptations->firstWhere('session_number', 3);
    $sessionOneConsequences = collect($sessionOne->choice_consequence_map['branching_consequences']);
    $sessionOneEnd = $sessionOneConsequences->firstWhere('choice_id', 'S1_C4');

    expect($sessionOne->playableCut->moments->first()->scene_card['choice_ids'])->toBe(['S1_C1'])
        ->and($sessionOne->playableCut->moments->get(1)->scene_card['choice_ids'])->toBe(['S1_C2'])
        ->and($sessionOne->playableCut->moments->last()->scene_card['choice_ids'])->toBe(['S1_C4'])
        ->and($sessionTwo->playableCut->moments->last()->scene_card['choice_ids'])->toBe(['S2_C4'])
        ->and($sessionThree->playableCut->moments->last()->scene_card['choice_ids'])->toBe(['S3_C4'])
        ->and($sessionOne->session_close_design['session_end_choice']['option_b']['text'])->toBe('You chose to trace the signal to its source.')
        ->and($sessionTwo->session_close_design['session_end_choice']['option_a']['text'])->toBe('You chose to follow the origin all the way.')
        ->and($sessionThree->session_close_design['session_end_choice']['option_a']['text'])->toBe('You chose to preserve the message.')
        ->and($sessionOneEnd['paths'][1]['next_session_opening'])
        ->toBe('The coordinates point toward a location the station\'s maps insist does not exist.');
});

it('is idempotent and keeps a loadable session context', function (): void {
    $this->seed(CompletionCardsTestStorySeeder::class);

    $stories = Story::query()->where('slug', CompletionCardsTestStorySeeder::SLUG)->get();
    $story = $stories->first()->load(['adaptation.sessionAdaptations']);
    $context = app(ChaosEngineService::class)->loadSessionContext($story, 1, null);

    expect($stories)->toHaveCount(1)
        ->and($story->adaptation->sessionAdaptations)->toHaveCount(3)
        ->and($context)->not->toBeNull()
        ->and($context['runtime_prompt'])->not->toBeEmpty();
});

it('completes session one in three playable turns and compiles a session completion card', function (): void {
    $story = Story::query()
        ->where('slug', CompletionCardsTestStorySeeder::SLUG)
        ->with(['adaptation.sessionAdaptations.playableCut.moments'])
        ->firstOrFail();

    $user = User::factory()->withCompleteProfile()->markEmailAsVerified()->create([
        'is_active' => true,
    ]);
    $game = Game::query()->create([
        'story_id' => $story->id,
        'user_id' => $user->id,
        'current_session_number' => 1,
        'model' => 'claude-sonnet-4-6',
        'alignment_scaffold' => ['chaotic' => 0, 'lawful' => 0, 'neutral' => 0],
    ]);

    $sessions = $story->adaptation->sessionAdaptations;
    $resolver = app(PlayableMomentResolver::class);
    $resolver->initializeForSession($game, $sessions->firstWhere('session_number', 1));

    $opening = $game->prompts()->create([
        'session_number' => 1,
        'response' => '<p>The dish ticks. The pulse wants an answer.</p>',
        'choices' => [],
    ]);

    $afterIdentity = playLastSignalTurn(
        $resolver,
        $game,
        $opening,
        $sessions,
        'Inspect the damaged transmitter.',
        momentKey: 'S1_M01',
        definingChoiceId: 'S1_C1',
        definingPathLabel: 'B',
        definingChoiceLine: 'You inspected the transmitter before answering it.',
        ledger: 'You inspected the transmitter before answering it.',
        knowledge: 'The pulse is a repeating last call, not noise.',
        style: 'You test the hardware before you spend a reply.',
        alignment: ['chaotic' => 0, 'lawful' => 1, 'neutral' => 0],
    );

    expect($game->fresh()->current_session_complete)->toBeFalse();

    $afterMethod = playLastSignalTurn(
        $resolver,
        $game->fresh(),
        $afterIdentity,
        $sessions,
        'Decode the transmitter buffer.',
        momentKey: 'S1_M02',
        definingChoiceId: 'S1_C2',
        definingPathLabel: 'A',
        definingChoiceLine: 'You decoded the transmitter buffer.',
        ledger: 'You read what the dish had already overheard.',
        knowledge: 'The buffer already stored fragments of the pulse.',
        style: 'You gather evidence before you spend a reply.',
        alignment: ['chaotic' => 0, 'lawful' => 1, 'neutral' => 0],
    );

    expect($game->fresh()->current_session_complete)->toBeFalse();

    playLastSignalTurn(
        $resolver,
        $game->fresh(),
        $afterMethod,
        $sessions,
        'Trace the signal to its source.',
        momentKey: 'S1_M03',
        definingChoiceId: 'S1_C4',
        definingPathLabel: 'B',
        definingChoiceLine: 'You chose to trace the signal to its source.',
        ledger: 'You refused to let the pulse stay anonymous.',
        knowledge: 'The coordinates point off every station map.',
        style: 'You follow a signal into the dark.',
        alignment: ['chaotic' => 1, 'lawful' => 1, 'neutral' => 0],
        promise: 'The denied location is still waiting.',
    );

    $completed = CompletionReceiptFixtures::loadForShow($game->fresh());
    $receipt = app(CompletionReceiptBuilder::class)->build($completed);

    expect($completed->current_session_complete)->toBeTrue()
        ->and($completed->defining_path_label)->toBe('B')
        ->and($receipt['mode'])->toBe('session')
        ->and($receipt['final_line'])->toBe('The last pulse dies in the dish. The station is no longer the only thing that remembers what you chose.')
        ->and($receipt['commitment'])->toBe('You chose to trace the signal to its source.')
        ->and($receipt['what_waits']['next_opens'])->toBe('The coordinates point toward a location the station\'s maps insist does not exist.')
        ->and($receipt['becoming']['identity'])->toBe('The one who listens before walking away.')
        ->and($receipt['world_remembers'])->not->toBeEmpty()
        ->and(json_encode($receipt))->not->toContain('chaotic')
        ->and(json_encode($receipt))->not->toContain('lawful')
        ->and(json_encode($receipt))->not->toContain('neutral');
});

it('completes session two in three playable turns and compiles a session completion card', function (): void {
    $story = Story::query()
        ->where('slug', CompletionCardsTestStorySeeder::SLUG)
        ->with(['adaptation.sessionAdaptations.playableCut.moments'])
        ->firstOrFail();

    $user = User::factory()->withCompleteProfile()->markEmailAsVerified()->create([
        'is_active' => true,
    ]);
    $game = Game::query()->create([
        'story_id' => $story->id,
        'user_id' => $user->id,
        'current_session_number' => 2,
        'model' => 'claude-sonnet-4-6',
        'alignment_scaffold' => ['chaotic' => 0, 'lawful' => 3, 'neutral' => 0],
        'defining_choice_id' => 'S1_C4',
        'defining_path_label' => 'B',
        'defining_choice_line' => 'You chose to trace the signal to its source.',
        'world_state' => [
            'knowledge' => ['You restored the abandoned station.', 'You followed the signal.'],
            'player_style' => ['You listen, then you stay.'],
            'emotional_ledger' => [
                ['category' => 'promises_made', 'entry' => 'You restored the abandoned station.'],
                ['category' => 'secrets', 'entry' => 'You followed the signal.'],
            ],
        ],
        'symbolic_memory' => 'The dish remembered your hands.',
    ]);

    $sessions = $story->adaptation->sessionAdaptations;
    $resolver = app(PlayableMomentResolver::class);
    $resolver->initializeForSession($game, $sessions->firstWhere('session_number', 2));

    $opening = $game->prompts()->create([
        'session_number' => 2,
        'response' => '<p>The maps are wrong. The source is already here.</p>',
        'choices' => [],
    ]);

    $afterMeeting = playLastSignalTurn(
        $resolver,
        $game,
        $opening,
        $sessions,
        'Press toward the origin.',
        momentKey: 'S2_M01',
        definingChoiceId: 'S2_C1',
        definingPathLabel: 'A',
        definingChoiceLine: 'You followed the signal to its source.',
        ledger: 'The source of the signal is no longer a theory.',
        knowledge: 'The denied location is a room.',
        style: 'You stay long enough to hear the truth.',
        alignment: ['chaotic' => 1, 'lawful' => 0, 'neutral' => 0],
    );

    expect($game->fresh()->current_session_complete)->toBeFalse();

    $afterMethod = playLastSignalTurn(
        $resolver,
        $game->fresh(),
        $afterMeeting,
        $sessions,
        'Enter the denied room.',
        momentKey: 'S2_M02',
        definingChoiceId: 'S2_C2',
        definingPathLabel: 'A',
        definingChoiceLine: 'You entered the denied room.',
        ledger: 'The blank on the map now has a floor.',
        knowledge: 'The denied threshold can be crossed.',
        style: 'You keep walking toward the unknown.',
        alignment: ['chaotic' => 1, 'lawful' => 0, 'neutral' => 0],
    );

    expect($game->fresh()->current_session_complete)->toBeFalse();

    playLastSignalTurn(
        $resolver,
        $game->fresh(),
        $afterMethod,
        $sessions,
        'Follow the origin all the way.',
        momentKey: 'S2_M03',
        definingChoiceId: 'S2_C4',
        definingPathLabel: 'A',
        definingChoiceLine: 'You chose to follow the origin all the way.',
        ledger: 'You refused to stop short of the source.',
        knowledge: 'The last room is the origin itself.',
        style: 'You follow a signal into the dark.',
        alignment: ['chaotic' => 1, 'lawful' => 0, 'neutral' => 0],
        promise: 'The last frequency is still waiting.',
    );

    $completed = CompletionReceiptFixtures::loadForShow($game->fresh());
    $receipt = app(CompletionReceiptBuilder::class)->build($completed);

    expect($completed->current_session_complete)->toBeTrue()
        ->and($receipt['mode'])->toBe('session')
        ->and($receipt['final_line'])->toBe('The denied site keeps the shape of your second commitment. The last room is already listening.')
        ->and($receipt['commitment'])->toBe('You chose to follow the origin all the way.')
        ->and($receipt['what_waits']['next_opens'])->toBe('The last room is the origin itself, still carrying the unfinished frequency.')
        ->and($receipt)->not->toHaveKey('what_settled');
});

it('completes session three in three playable turns and compiles a story completion card', function (): void {
    $story = Story::query()
        ->where('slug', CompletionCardsTestStorySeeder::SLUG)
        ->with(['adaptation.sessionAdaptations.playableCut.moments'])
        ->firstOrFail();

    $user = User::factory()->withCompleteProfile()->markEmailAsVerified()->create([
        'is_active' => true,
    ]);
    $game = Game::query()->create([
        'story_id' => $story->id,
        'user_id' => $user->id,
        'current_session_number' => 3,
        'model' => 'claude-sonnet-4-6',
        'alignment_scaffold' => ['chaotic' => 0, 'lawful' => 3, 'neutral' => 0],
        'defining_choice_id' => 'S2_C4',
        'defining_path_label' => 'A',
        'defining_choice_line' => 'You chose to follow the origin all the way.',
        'world_state' => [
            'knowledge' => ['You restored the abandoned station.', 'You followed the signal.'],
            'player_style' => ['You listen, then you stay.'],
            'emotional_ledger' => [
                ['category' => 'promises_made', 'entry' => 'You restored the abandoned station.'],
                ['category' => 'secrets', 'entry' => 'You followed the signal.'],
            ],
        ],
        'symbolic_memory' => "The dish remembered your hands.\n\nOne dead frequency. One saved message. And the knowledge that someone heard it.",
    ]);

    $sessions = $story->adaptation->sessionAdaptations;
    $resolver = app(PlayableMomentResolver::class);
    $resolver->initializeForSession($game, $sessions->firstWhere('session_number', 3));

    $opening = $game->prompts()->create([
        'session_number' => 3,
        'response' => '<p>The last room is already carrying the unfinished frequency.</p>',
        'choices' => [],
    ]);

    $afterEntry = playLastSignalTurn(
        $resolver,
        $game,
        $opening,
        $sessions,
        'Open the remaining channel.',
        momentKey: 'S3_M01',
        definingChoiceId: 'S3_C1',
        definingPathLabel: 'A',
        definingChoiceLine: 'You opened the remaining channel.',
        ledger: 'The last room is no longer sealed against the frequency.',
        knowledge: 'The remaining words have a live path into the room.',
        style: 'You stay long enough to hear the truth.',
        alignment: ['chaotic' => 1, 'lawful' => 0, 'neutral' => 0],
    );

    expect($game->fresh()->current_session_complete)->toBeFalse();

    $afterHearing = playLastSignalTurn(
        $resolver,
        $game->fresh(),
        $afterEntry,
        $sessions,
        'Translate the remaining words.',
        momentKey: 'S3_M02',
        definingChoiceId: 'S3_C2',
        definingPathLabel: 'B',
        definingChoiceLine: 'You translated the remaining words.',
        ledger: 'You made the last words readable before you spent them.',
        knowledge: 'Meaning arrives before the frequency dies.',
        style: 'You listen, then you stay.',
        alignment: ['chaotic' => 0, 'lawful' => 1, 'neutral' => 0],
    );

    expect($game->fresh()->current_session_complete)->toBeFalse();

    playLastSignalTurn(
        $resolver,
        $game->fresh(),
        $afterHearing,
        $sessions,
        'Preserve the message.',
        momentKey: 'S3_M03',
        definingChoiceId: 'S3_C4',
        definingPathLabel: 'A',
        definingChoiceLine: 'You chose to preserve the message.',
        ledger: 'You chose whether the message should survive.',
        knowledge: 'The message is copied before the frequency dies.',
        style: 'The witness who stayed long enough to hear the truth.',
        alignment: ['chaotic' => 0, 'lawful' => 1, 'neutral' => 0],
    );

    $completed = CompletionReceiptFixtures::loadForShow($game->fresh());
    $receipt = app(CompletionReceiptBuilder::class)->build($completed);

    expect($completed->current_session_complete)->toBeTrue()
        ->and($receipt['mode'])->toBe('story')
        ->and($receipt['final_line'])->toBe('The frequency goes still. What you did with its last words is the only monument the station keeps.')
        ->and($receipt['what_settled'])->not->toBeEmpty()
        ->and($receipt['record'])->not->toBeEmpty()
        ->and($receipt['became']['identity'])->toBe('The one who listens before walking away.')
        ->and($receipt['leaves_behind'])->toBe('One dead frequency. One saved message. And the knowledge that someone heard it.')
        ->and($receipt)->not->toHaveKey('commitment');
});

function playLastSignalTurn(
    PlayableMomentResolver $resolver,
    Game $game,
    Prompt $source,
    Illuminate\Support\Collection $sessions,
    string $playerText,
    string $momentKey,
    string $definingChoiceId,
    string $definingPathLabel,
    string $definingChoiceLine,
    string $ledger,
    string $knowledge,
    string $style,
    array $alignment,
    ?string $promise = null,
): Prompt {
    return $resolver->applyPlayerTurn(
        $game,
        $source,
        $playerText,
        lastSignalTurnResult(
            momentKey: $momentKey,
            exit: true,
            definingChoiceId: $definingChoiceId,
            definingPathLabel: $definingPathLabel,
            definingChoiceLine: $definingChoiceLine,
            ledger: $ledger,
            knowledge: $knowledge,
            style: $style,
            alignment: $alignment,
            promise: $promise,
        ),
        [],
        $sessions,
    );
}

/**
 * @param  array{chaotic: int, lawful: int, neutral: int}  $alignment
 * @return array<string, mixed>
 */
function lastSignalTurnResult(
    string $momentKey,
    bool $exit,
    string $definingChoiceId,
    string $definingPathLabel,
    string $definingChoiceLine,
    string $ledger,
    string $knowledge,
    string $style,
    array $alignment,
    ?string $promise = null,
): array {
    return [
        'response' => '<p>The room answers.</p>',
        'state_delta' => [
            'location' => '',
            'conditions' => [],
            'items' => [],
            'object_states' => [],
            'relationship_updates' => [],
            'world_flags' => [],
            'knowledge' => [$knowledge],
            'notes' => [],
            'player_style' => [$style],
            'unresolved_promises' => $promise !== null ? [$promise] : [],
            'emotional_ledger_entries' => [
                ['category' => 'promises_made', 'entry' => $ledger],
            ],
        ],
        'alignment_tally_delta' => $alignment,
        'is_climactic_choice' => true,
        'defining_choice_id' => $definingChoiceId,
        'defining_path_label' => $definingPathLabel,
        'defining_choice_line' => $definingChoiceLine,
        'symbolic_memory_update' => '',
        'session_complete' => $exit,
        'moment_progress' => [
            'moment_key' => $momentKey,
            'exit_condition_met' => $exit,
            'route_relation' => 'advanced',
            'impact_scope' => 'persistent',
            'moment_footnote' => 'They want the world to keep the choice they just made.',
        ],
    ];
}
