<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Authentication\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Response;

final class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token): Response
    {
        return inertia('User/Authentication/ResetPassword', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        auth('user')->logout();

        return to_route('user.authentication.login.create')
            ->with('success', __($status));
    }
}
