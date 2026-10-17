<?php

declare(strict_types=1);

use App\Actions\Game\CreateGameAction;
use App\Models\Story;
use App\Models\User;
use App\Services\Adaptation\StoryGameResetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

it('starts a new Peter Pan game on Haiku 5.5 and leaves every other title on Haiku 4.5', function () {
    config(['prism.providers.anthropic.api_key' => 'test-anthropic-key']);

    $user = User::factory()->create();
    $peterPan = Story::factory()->create(['slug' => 'peter-pan']);
    $other = Story::factory()->create();

    $peterGame = app(CreateGameAction::class)->handle($user, $peterPan);
    $otherGame = app(CreateGameAction::class)->handle($user, $other);

    expect($peterGame->model)->toBe('claude-haiku-5-5')
        ->and($otherGame->model)->toBe('claude-haiku-4-5');

    app(StoryGameResetService::class)->reset($peterGame);
    app(StoryGameResetService::class)->reset($otherGame);

    expect($peterGame->fresh()->model)->toBe('claude-haiku-5-5')
        ->and($otherGame->fresh()->model)->toBe('claude-haiku-4-5');
});
