<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * Alias custom middleware
         */
        $middleware->alias([
            'guard.profile-is-completed' => App\Http\Middleware\Guard\EnsureProfileIsCompleted::class,
            'guard.profile-is-incompleted' => App\Http\Middleware\Guard\EnsureProfileIsIncompleted::class,
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
                /** @var App\Models\User $user */
                $user = $request->user('user');

                return $user->is_profile_completed
                    ? route('user.dashboard.index')
                    : route('user.authentication.complete-profile.edit');
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
