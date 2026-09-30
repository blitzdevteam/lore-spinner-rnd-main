<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Actions\User\CancelSubscriptionAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class CancelSubscriptionController extends Controller
{
    public function __invoke(
        #[CurrentUser] User $user,
        CancelSubscriptionAction $cancelSubscription,
    ): RedirectResponse {
        $result = $cancelSubscription->handle($user);

        return to_route('pricing')->with(
            $result->successful ? 'success' : 'error',
            $result->message,
        );
    }
}
