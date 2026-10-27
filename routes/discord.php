<?php

declare(strict_types=1);

use App\Http\Controllers\Discord\ActivityController;
use App\Http\Controllers\Discord\InteractionController;
use Illuminate\Support\Facades\Route;

Route::get('discord/activity', ActivityController::class)
    ->middleware('web')
    ->name('discord.activity');

Route::post('discord/activity/begin', [ActivityController::class, 'begin'])
    ->middleware('web')
    ->name('discord.activity.begin');

Route::post('discord/interactions', InteractionController::class)
    ->name('discord.interactions');
