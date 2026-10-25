<?php

declare(strict_types=1);

use App\Http\Controllers\Discord\InteractionController;
use Illuminate\Support\Facades\Route;

Route::post('discord/interactions', InteractionController::class)
    ->name('discord.interactions');
