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

        $isFirstLogin = ! $user->hasCompletedSetup();

        if ($isFirstLogin) {
            $completeInvestorAccess->handle(
                $user,
                $request->string('email')->toString(),
                $request->string('password')->toString(),
            );
        }

        $user->refresh();
        $this->loginInvestor($request, $user);

        if ($isFirstLogin) {
            return to_route('index')->with('investor_welcome', $this->welcomePayload($user));
        }

        return to_route('index')->with('success', 'You are signed in with investor access.');
    }

    /**
     * @return array{plan_name: string, duration: string, expires_on: string}
     */
    private function welcomePayload(User $user): array
    {
        $user->loadMissing('plan');
        $months = max(1, (int) $user->duration_months);

        return [
            'plan_name' => $user->plan?->name ?? 'Your plan',
            'duration' => User::durationOptions()[$months] ?? ($months === 1 ? '1 month' : $months.' months'),
            'expires_on' => $user->expires_at?->format('F j, Y') ?? '',
        ];
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
