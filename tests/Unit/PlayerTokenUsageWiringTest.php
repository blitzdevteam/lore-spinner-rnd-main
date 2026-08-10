<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('wires persistent token recording only into player gameplay actions', function () {
    $opening = File::get(app_path('Actions/Game/ProcessGameOpeningAction.php'));
    $turn = File::get(app_path('Actions/Game/ProcessGameTurnAction.php'));

    expect($opening)->toContain('PlayerTokenUsageRecorder')
        ->and($turn)->toContain('PlayerTokenUsageRecorder');

    foreach (File::allFiles(app_path('Jobs/Adaptation')) as $file) {
        expect($file->getContents())->not->toContain('PlayerTokenUsageRecorder');
    }

    foreach (File::allFiles(app_path('Ai/Adaptation')) as $file) {
        expect($file->getContents())->not->toContain('PlayerTokenUsageRecorder');
    }
});
