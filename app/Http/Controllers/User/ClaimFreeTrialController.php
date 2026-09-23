<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class ClaimFreeTrialController extends Controller
{
    public function __invoke(
        #[CurrentUser] User $user,
        SubscriptionEntitlementService $entitlements,
    ): RedirectResponse {
        $result = $entitlements->claimFreeTrial($user);

        return to_route('pricing')->with(
            $result->granted ? 'success' : 'error',
            $result->message,
        );
    }
}
