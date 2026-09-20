<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Actions\Investor\CompleteInvestorAccessAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\CompleteInvestorAccessRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

final class InvestorMagicLoginController extends Controller
{
    public function show(Request $request, User $user): RedirectResponse|Response
    {
        if (! $user->isInvestor()) {
            abort(404);
        }

        if (! $user->hasActiveAccess()) {
            return $this->expiredResponse();
        }

        if (! $user->hasCompletedSetup()) {
            return inertia('User/InvestorAccess', [
                'firstName' => $user->first_name,
                'submitUrl' => $user->magicLoginUrl(),
            ]);
        }

        $this->loginInvestor($request, $user);

        return to_route('index')->with('success', 'You are signed in with investor access.');
    }

    public function store(
        CompleteInvestorAccessRequest $request,
        User $user,
        CompleteInvestorAccessAction $completeInvestorAccess,
    ): RedirectResponse {
        if (! $user->isInvestor()) {
            abort(404);
        }

        if (! $user->hasActiveAccess()) {
            return $this->expiredResponse();
        }

        if (! $user->hasCompletedSetup()) {
            $completeInvestorAccess->handle(
                $user,
                $request->string('email')->toString(),
                $request->string('password')->toString(),
            );
        }

        $this->loginInvestor($request, $user->refresh());

        return to_route('index')->with('success', 'Your investor account is ready.');
    }

    private function loginInvestor(Request $request, User $user): void
    {
        if (auth('user')->check()) {
            auth('user')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        auth('user')->login($user, remember: true);
        $request->session()->regenerate();
    }

    private function expiredResponse(): RedirectResponse
    {
        return to_route('pricing')->with(
            'warning',
            'This investor access link has expired or been blocked. Choose a plan to keep playing.',
        );
    }
}
