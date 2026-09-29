<?php

declare(strict_types=1);

namespace App\Http\Middleware\Guard;

use App\Actions\User\AssignUniqueUsernameAction;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureProfileIsCompleted
{
    public function __construct(private AssignUniqueUsernameAction $assignUniqueUsername) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('user');

        if ($user instanceof User && ! $user->is_profile_completed) {
            $this->assignUniqueUsername->handle($user);
        }

        return $next($request);
    }
}
