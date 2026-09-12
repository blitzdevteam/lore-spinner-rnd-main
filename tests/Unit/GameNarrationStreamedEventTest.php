<?php

declare(strict_types=1);

use App\Events\GameNarrationStreamed;
use App\Models\Game;
use App\Models\Prompt;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on the private game channel under a stable event name', function (): void {
    $event = new GameNarrationStreamed('01HGAME', '01HSOURCE', GameNarrationStreamed::PHASE_READY);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and((string) $channels[0]->name)->toBe('private-game.01HGAME')
        ->and($event->broadcastAs())->toBe('game.narration.streamed')
        ->and(GameNarrationStreamed::channelName('01HGAME'))->toBe('game.01HGAME');
});

it('sends only the phase envelope for ready and delta', function (): void {
    $ready = new GameNarrationStreamed('g1', 's1', GameNarrationStreamed::PHASE_READY, null, 'Open the door');
    $delta = new GameNarrationStreamed('g1', 's1', GameNarrationStreamed::PHASE_DELTA, 'The hinge ');

    expect($ready->broadcastWith())->toBe([
        'game_id' => 'g1',
        'source_prompt_id' => 's1',
        'phase' => 'ready',
        'source_prompt_value' => 'Open the door',
    ])->and($delta->broadcastWith())->toBe([
        'game_id' => 'g1',
        'source_prompt_id' => 's1',
        'phase' => 'delta',
        'text' => 'The hinge ',
    ]);
});

it('carries the committed prompt and the game state the play page needs on done', function (): void {
    $prompt = new Prompt;
    $prompt->forceFill([
        'id' => '01HRESPONSE',
        'game_id' => 'g1',
        'session_number' => 2,
        'prompt' => null,
        'response' => '<p>The hinge gives.</p>',
        'choices' => [
            ['text' => 'Step through', 'canonical_choice_id' => 'S2_C01', 'canonical_path_label' => 'A'],
            'Wait',
        ],
    ]);

    $game = new Game;
    $game->forceFill([
        'current_session_number' => 2,
        'current_session_complete' => true,
        'current_playable_moment_id' => 7,
    ]);

    $payload = (new GameNarrationStreamed(
        'g1',
        's1',
        GameNarrationStreamed::PHASE_DONE,
        null,
        'Open the door',
        $prompt,
        $game,
    ))->broadcastWith();

    expect($payload['phase'])->toBe('done')
        ->and($payload['source_prompt_value'])->toBe('Open the door')
        ->and($payload['prompt'])->toMatchArray([
            'id' => '01HRESPONSE',
            'game_id' => 'g1',
            'session_number' => 2,
            'prompt' => '',
            'response' => '<p>The hinge gives.</p>',
        ])
        ->and($payload['prompt']['choices'])->toBe([
            ['text' => 'Step through', 'canonical_choice_id' => 'S2_C01', 'canonical_path_label' => 'A'],
            'Wait',
        ])
        ->and($payload['game'])->toBe([
            'current_session_number' => 2,
            'current_session_complete' => true,
            'uses_playable_moments' => true,
        ]);
});

it('reports the failure message on error', function (): void {
    $payload = (new GameNarrationStreamed(
        'g1',
        's1',
        GameNarrationStreamed::PHASE_ERROR,
        null,
        null,
        null,
        null,
        'Narration hiccuped — please retry this turn.',
    ))->broadcastWith();

    expect($payload)->toBe([
        'game_id' => 'g1',
        'source_prompt_id' => 's1',
        'phase' => 'error',
        'message' => 'Narration hiccuped — please retry this turn.',
    ]);
});
