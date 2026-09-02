<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('does not dump the full runtime system prompt to the default log channel on turns', function () {
    $source = File::get(app_path('Actions/Game/ProcessGameTurnAction.php'));

    expect($source)->not->toContain("Log::info('game.turn.system_prompt'")
        ->and($source)->toContain('system_prompt_bytes');
});
