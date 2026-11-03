<?php

declare(strict_types=1);

use App\Http\Controllers\Discord\ActivityController;
use App\Http\Controllers\Discord\InteractionController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::get('discord/activity', ActivityController::class)
        ->name('discord.activity');

    Route::post('discord/activity/session', [ActivityController::class, 'session'])
        ->middleware('throttle:20,1')
        ->name('discord.activity.session');

    Route::middleware('auth:user')->group(function (): void {
        Route::get('discord/activity/lobby', [ActivityController::class, 'showLobby'])
            ->name('discord.activity.lobby.show');

        Route::post('discord/activity/lobby', [ActivityController::class, 'storeLobby'])
            ->name('discord.activity.lobby.store');
    });
});

Route::post('discord/interactions', InteractionController::class)
    ->name('discord.interactions');
