<?php

declare(strict_types=1);

namespace App\Http\Middleware\Guard;

use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveSubscription
{
    public function __construct(private readonly SubscriptionEntitlementService $entitlements) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('user');

        if ($user instanceof User && $this->entitlements->hasPlayAccess($user)) {
            return $next($request);
        }

        return $this->reject($request);
    }

    private function reject(Request $request): RedirectResponse|JsonResponse
    {
        $message = 'Choose an active subscription plan to start or continue a single-player story.';

        if (
            $request->expectsJson()
            || (
                $request->is('user/games/*/prompt')
                && str_contains((string) $request->headers->get('Accept'), 'application/x-ndjson')
            )
        ) {
            return response()->json([
                'message' => $message,
                'reason' => 'subscription_required',
                'pricing_url' => route('pricing'),
            ], 403);
        }

        return to_route('pricing')->with('warning', $message);
    }
}
