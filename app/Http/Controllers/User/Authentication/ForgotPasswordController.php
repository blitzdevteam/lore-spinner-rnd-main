<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Authentication\SendPasswordResetLinkRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final class ForgotPasswordController extends Controller
{
    public function create(): Response
    {
        return inertia('User/Authentication/ForgotPassword', [
            'resetLinkSent' => session()->has('success'),
            'email' => (string) old('email', ''),
        ]);
    }

    public function store(SendPasswordResetLinkRequest $request): RedirectResponse
    {
        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return back()
                ->withInput($request->only('email'))
                ->with('error', 'We could not send the reset email. Please try again in a moment.');
        }

        if ($status !== Password::RESET_LINK_SENT) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return back()
            ->withInput($request->only('email'))
            ->with('success', __($status));
    }
}
