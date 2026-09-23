<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Middleware;
use Override;

final class HandleInertiaRequests extends Middleware
{
    public function __construct(private readonly SubscriptionEntitlementService $subscriptionEntitlements) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    #[Override]
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'error' => $request->session()->get('error') ? Arr::wrap($request->session()->get('error')) : [],
                'success' => $request->session()->get('success') ? Arr::wrap($request->session()->get('success')) : [],
                'warning' => $request->session()->get('warning') ? Arr::wrap($request->session()->get('warning')) : [],
                'story_complete' => (bool) $request->session()->get('story_complete', false),
                'investor_welcome' => $request->session()->get('investor_welcome'),
            ],
            'auth' => function () use ($request) {
                $user = $request->user('user');

                if (! $user instanceof User) {
                    return null;
                }

                return [
                    ...$user->only([
                        'id',
                        'first_name',
                        'last_name',
                        'full_name',
                        'gender',
                        'username',
                        'email',
                        'avatar',
                        'bio',
                    ]),
                    'subscription_usage' => $this->subscriptionEntitlements->summary($user),
                ];
            },
            // Lightweight writer-auth flag for nav — avoids full model query on every page.
            'writerLoggedIn' => fn () => (bool) $request->user('writer'),
        ];
    }
}
