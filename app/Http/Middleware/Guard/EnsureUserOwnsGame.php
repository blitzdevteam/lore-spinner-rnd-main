<?php

declare(strict_types=1);

namespace App\Http\Middleware\Guard;

use App\Models\Game;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserOwnsGame
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $game = $request->route('game');
        $user = $request->user('user');

        abort_unless($game instanceof Game && $user instanceof User, 404);

        Gate::forUser($user)->authorize('view', $game);

        return $next($request);
    }
}
