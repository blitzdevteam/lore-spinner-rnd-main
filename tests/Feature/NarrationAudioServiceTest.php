<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Prompt;
use App\Services\NarrationAudioService;
use Illuminate\Support\Facades\Cache;

it('marks a recommended-voice render as pending and clears a stale failure', function (): void {
    $service = app(NarrationAudioService::class);
    $prompt = (new Prompt)->forceFill(['id' => 'prompt-opening-1']);

    Cache::put($service->errorKey('prompt-opening-1'), 'old failure', 60);

    $service->markPendingRecommended(new Game, $prompt);

    expect($service->isGenerating($prompt))->toBeTrue()
        ->and($service->failure($prompt))->toBeNull();
});
