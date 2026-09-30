<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use App\Support\ReturnToUrl;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ClaimFreeTrialController extends Controller
{
    public function __invoke(
        #[CurrentUser] User $user,
        Request $request,
        SubscriptionEntitlementService $entitlements,
    ): RedirectResponse {
        $result = $entitlements->claimFreeTrial($user);
        $returnTo = ReturnToUrl::fromRequest($request);

        if ($result->granted && $returnTo !== null) {
            return redirect()->to($returnTo)->with('success', $result->message);
        }

        return to_route('pricing', array_filter([
            'return_to' => $returnTo,
        ], static fn (?string $value): bool => $value !== null))->with(
            $result->granted ? 'success' : 'error',
            $result->message,
        );
    }
}
