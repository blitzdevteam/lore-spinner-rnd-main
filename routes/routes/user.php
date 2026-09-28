<?php

declare(strict_types=1);

use App\Http\Controllers\User;
use Illuminate\Support\Facades\Route;

Route::prefix('user')->name('user.')->group(function (): void {
    // Authentication
    Route::prefix('authentication')->name('authentication.')->group(function () {
        Route::middleware('guest')->group(function () {
            Route::prefix('register')->name('register.')->controller(User\Authentication\RegisterController::class)->group(function () {
                Route::get('/', 'create')->name('create');
                Route::post('/', 'store')->name('store');
            });
            Route::prefix('login')->name('login.')->controller(User\Authentication\LoginController::class)->group(function () {
                Route::get('/', 'create')->name('create');
                Route::post('/', 'store')->name('store');
            });
            Route::prefix('forgot-password')->name('forgot-password.')->controller(User\Authentication\ForgotPasswordController::class)->group(function () {
                Route::get('/', 'create')->name('create');
                Route::post('/', 'store')->name('store');
            });
        });
        Route::prefix('reset-password')->name('reset-password.')->controller(User\Authentication\ResetPasswordController::class)->group(function () {
            Route::get('{token}', 'create')->name('create');
            Route::post('/', 'store')->name('store');
        });
        Route::middleware('auth:user')->group(function () {
            Route::get('account-created', [User\Authentication\RegisterController::class, 'accountCreated'])
                ->name('account-created');
            Route::prefix('complete-profile')
                ->middleware(['guard.profile-is-incompleted'])
                ->singleton('complete-profile', User\Authentication\CompleteProfileController::class)
                ->except('show');
            Route::delete('logout', [User\Authentication\LogoutController::class, 'destroy'])->name('logout');
        });
    });

    Route::middleware('auth:user')->get('checkout/{plan:slug}', User\CheckoutController::class)
        ->name('checkout');

    Route::middleware('auth:user')->post('free-trial', User\ClaimFreeTrialController::class)
        ->name('free-trial.store');

    Route::middleware('auth:user')
        ->get('games/{game}/tts/{prompt}', User\Game\TextToSpeechController::class)
        ->name('games.tts');

    Route::middleware([
        'auth:user',
        'guard.user-owns-game',
        'guard.profile-is-completed',
        'guard.subscription-is-active',
    ])->group(function (): void {
        Route::get('games/{game}', [User\GameController::class, 'show'])->name('games.show');

        Route::post('games/{game}/begin', [User\GameController::class, 'begin'])
            ->name('games.begin');

        Route::post('games/{game}/reset', [User\GameController::class, 'reset'])
            ->name('games.reset');

        Route::post('games/{game}/next-session', [User\GameController::class, 'nextSession'])
            ->name('games.next-session');

        Route::singleton('games.prompt', User\Game\PromptController::class)
            ->creatable()
            ->only(['store']);

        Route::post('games/{game}/recap', User\Game\RecapController::class)
            ->name('games.recap');
    });

    // Dashboard
    Route::middleware([
        'auth:user',
        'guard.profile-is-completed',
    ])
        ->group(function () {
            Route::get('dashboard', User\DashboardController::class)->name('dashboard.index');

            Route::post('bookmarks/{story}', [User\BookmarkController::class, 'toggle'])
                ->name('bookmarks.toggle');

            Route::get('achievements', User\AchievementController::class)
                ->name('achievements.index');

            Route::middleware('guard.subscription-is-active')->group(function (): void {
                Route::resource('games', User\GameController::class)
                    ->only(['index', 'store']);

                Route::post('games/transcribe', User\Game\TranscribeController::class)
                    ->name('games.transcribe');
            });

            require __DIR__.'/voice-lab.php';
        });
});
