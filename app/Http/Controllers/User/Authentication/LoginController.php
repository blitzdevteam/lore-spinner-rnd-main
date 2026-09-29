<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Authentication;

use App\Actions\Authentication\LoginAuthenticatableGuardAction;
use App\Actions\User\MarkPlayerWelcomeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Authentication\StoreLoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

final class LoginController extends Controller
{
    public function create(): Response
    {
        return inertia('User/Authentication/Login');
    }

    public function store(
        StoreLoginRequest $request,
        LoginAuthenticatableGuardAction $loginAuthenticatableGuard,
        MarkPlayerWelcomeAction $markPlayerWelcome,
    ): RedirectResponse {
        /** @var User|false $user */
        $user = $loginAuthenticatableGuard->handle(
            'user',
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        if ($user === false) {
            return back()
                ->with('error', 'Credentials do not match our records')
                ->onlyInput('email');
        }

        $welcome = $markPlayerWelcome->handle($user);
        $redirect = redirect()->intended(route('user.dashboard.index'));

        if ($welcome !== null) {
            return $redirect->with('player_welcome', $welcome);
        }

        return $redirect->with('success', 'You have been logged in successfully');
    }
}
