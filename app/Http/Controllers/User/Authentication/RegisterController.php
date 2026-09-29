<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Authentication;

use App\Actions\Authentication\CreateAuthenticatableGuardAction;
use App\Actions\User\AssignUniqueUsernameAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Authentication\StoreRegisterRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

final class RegisterController extends Controller
{
    public function create(): Response
    {
        return inertia('User/Authentication/Register');
    }

    public function store(
        StoreRegisterRequest $request,
        CreateAuthenticatableGuardAction $createAuthenticatableGuard,
        AssignUniqueUsernameAction $assignUniqueUsername,
    ): RedirectResponse {
        $email = $request->string('email')->toString();
        $password = $request->string('password')->toString();

        $user = $createAuthenticatableGuard->handle('user', $email, $password);
        $user->update([
            'first_name' => $request->string('first_name')->toString(),
            'last_name' => $request->string('last_name')->toString(),
            'gender' => $request->string('gender')->toString(),
        ]);
        $assignUniqueUsername->handle($user);

        auth('user')->login($user, remember: true);

        return redirect()
            ->intended(route('user.dashboard.index'))
            ->with('success', 'Your account has been created');
    }
}
