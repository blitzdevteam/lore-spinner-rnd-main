<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            Route::middleware([])->group(base_path('routes/discord.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * Alias custom middleware
         */
        $middleware->alias([
            'guard.profile-is-completed' => App\Http\Middleware\Guard\EnsureProfileIsCompleted::class,
            'guard.profile-is-incompleted' => App\Http\Middleware\Guard\EnsureProfileIsIncompleted::class,
            'guard.subscription-is-active' => App\Http\Middleware\Guard\EnsureActiveSubscription::class,
            'guard.user-owns-game' => App\Http\Middleware\Guard\EnsureUserOwnsGame::class,
        ]);

        /**
         * Append custom middleware to the "web" middleware group
         */
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        /**
         * Custom redirects for guests and authenticated users
         */
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('user/*')) {
                return route('user.authentication.login.create');
            }

            if ($request->is('creator/*')) {
                return route('creator.authentication.login.create');
            }

            return route('index');
        });

        /**
         * Custom redirects for authenticated users
         */
        $middleware->redirectUsersTo(function (Request $request): string {
            if ($request->user('user')) {
                return route('user.dashboard.index');
            }

            if ($request->user('creator')) {
                return route('creator.dashboard.index');
            }

            return route('index');
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('backup:clean')->daily()->at('01:00');
        $schedule->command('backup:run')->daily()->at('01:30');
        $schedule->command('investors:expire-access')
            ->daily()
            ->withoutOverlapping();
        $schedule->command('multiplayer:lobbies:close-inactive')
            ->everyFiveMinutes()
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($response->getStatusCode() === 404 && ! $request->expectsJson()) {
                return Inertia::render('NotFound')
                    ->toResponse($request)
                    ->setStatusCode(404);
            }

            return $response;
        });
    })->create();
