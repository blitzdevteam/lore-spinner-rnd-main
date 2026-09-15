<?php

declare(strict_types=1);

use App\Enums\CompletionReceiptMode;
use App\Models\CompletionReceipt;
use App\Services\Runtime\CompletionReceiptRecorder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CompletionReceiptFixtures;

uses(DatabaseTransactions::class);

it('returns null when the session is still in progress', function () {
    $game = CompletionReceiptFixtures::game(gameAttributes: [
        'current_session_complete' => false,
    ]);

    expect(app(CompletionReceiptRecorder::class)->snapshot($game))->toBeNull()
        ->and(CompletionReceipt::query()->where('game_id', $game->id)->exists())->toBeFalse();
});

it('stores a session ceremony snapshot', function () {
    $game = CompletionReceiptFixtures::game();
    $row = app(CompletionReceiptRecorder::class)->snapshot($game);

    expect($row)->not->toBeNull()
        ->and($row->mode)->toBe(CompletionReceiptMode::Session)
        ->and($row->session_number)->toBe(1)
        ->and($row->receipt['final_line'])->toBe('The light waits; so, now, does Holmes.')
        ->and($row->receipt['commitment'])->toBe('You told Holmes you would go — but only after testing the signal once more in your mind.');
});

it('stores a story ceremony snapshot on the last session', function () {
    $game = CompletionReceiptFixtures::game(gameAttributes: [
        'current_session_number' => 2,
        'defining_choice_line' => 'I chose how much of it the world gets to know.',
    ]);

    $row = app(CompletionReceiptRecorder::class)->snapshot($game);

    expect($row->mode)->toBe(CompletionReceiptMode::Story)
        ->and($row->receipt['final_line'])->toBe('The case is solved; its meaning, and its measure, remain in your hands.')
        ->and($row->receipt)->not->toHaveKey('what_waits');
});

it('updates the same cycle session instead of duplicating it', function () {
    $game = CompletionReceiptFixtures::game();
    $recorder = app(CompletionReceiptRecorder::class);

    $first = $recorder->snapshot($game);
    $game->update(['defining_choice_line' => 'I know how the room is meant to kill. I have not entered it yet.']);
    $second = $recorder->snapshot($game->fresh());

    expect($second->id)->toBe($first->id)
        ->and(CompletionReceipt::query()->where('game_id', $game->id)->count())->toBe(1);
});
