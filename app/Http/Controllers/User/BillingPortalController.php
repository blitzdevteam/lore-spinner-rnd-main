<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class BillingPortalController extends Controller
{
    public function __invoke(#[CurrentUser] User $user): RedirectResponse
    {
        if (! $user->hasStripeId()) {
            return to_route('pricing');
        }

        return $user->redirectToBillingPortal(route('pricing'));
    }
}
