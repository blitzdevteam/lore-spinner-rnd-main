<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Authentication;

use App\Actions\Authentication\CreateAuthenticatableGuardAction;
use App\Actions\User\AssignUniqueUsernameAction;
use App\Actions\User\MarkPlayerWelcomeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Authentication\StoreRegisterRequest;
use App\Services\SubscriptionEntitlementService;
use App\Support\ReturnToUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

final class RegisterController extends Controller
{
    public function create(Request $request): Response
    {
        return inertia('User/Authentication/Register', [
            'returnTo' => ReturnToUrl::fromRequest($request),
        ]);
    }

    public function store(
        StoreRegisterRequest $request,
        CreateAuthenticatableGuardAction $createAuthenticatableGuard,
        AssignUniqueUsernameAction $assignUniqueUsername,
        MarkPlayerWelcomeAction $markPlayerWelcome,
        SubscriptionEntitlementService $entitlements,
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
        $user->refresh();

        $claim = $entitlements->claimFreeTrial($user);
        $welcome = $markPlayerWelcome->handle($user) ?? [
            'first_name' => mb_trim((string) $user->first_name),
        ];

        if ($claim->granted) {
            $summary = $entitlements->summary($user);

            if ($summary !== null) {
                $welcome['plan_name'] = $summary['plan']['name'];
                $welcome['actions_limit'] = $summary['actions']['limit'];
                $welcome['voice_minutes'] = $summary['voice']['limit_minutes'];
            }
        }

        $returnTo = ReturnToUrl::fromRequest($request);
        $redirect = $returnTo !== null
            ? redirect()->to($returnTo)
            : redirect()->intended(route('user.dashboard.index'));

        return $redirect->with('player_welcome', $welcome);
    }
}
