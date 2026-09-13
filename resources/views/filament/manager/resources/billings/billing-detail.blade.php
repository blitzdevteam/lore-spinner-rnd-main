@php
    /** @var \Laravel\Cashier\Subscription $subscription */
    /** @var array{
     *     invoices:list<array{
     *         id:string,
     *         number:string,
     *         date:\Carbon\CarbonInterface,
     *         total:string,
     *         status:string,
     *         hosted_invoice_url:?string
     *     }>,
     *     unavailable:?string
     * } $invoiceHistory */
    $user = $subscription->user;
    $priceIds = $subscription->items
        ->pluck('stripe_price')
        ->prepend($subscription->stripe_price)
        ->filter()
        ->unique()
        ->values();
    $planLabels = $priceIds
        ->map(static fn (string $priceId): string => \App\Enums\PricingPlan::fromStripePriceId($priceId)?->label() ?? 'Custom Stripe plan');
    $subscriptionStatus = $subscription->stripe_status;
    $paymentMethod = $user instanceof \App\Models\User && filled($user->pm_last_four)
        ? (is_string($user->pm_type) ? str($user->pm_type)->headline().' ' : 'Card ').'•••• '.$user->pm_last_four
        : 'Not synchronized';
@endphp

<div class="space-y-6">
    <section class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary-500 via-violet-400 to-info-400"></div>

        <div class="p-5 sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span @class([
                            'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
                            'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-300 dark:ring-success-400/20' => $subscriptionStatus === 'active',
                            'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-400/10 dark:text-info-300 dark:ring-info-400/20' => $subscriptionStatus === 'trialing',
                            'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-300 dark:ring-warning-400/20' => in_array($subscriptionStatus, ['past_due', 'incomplete'], true),
                            'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-300 dark:ring-danger-400/20' => $subscriptionStatus === 'unpaid',
                            'bg-gray-100 text-gray-700 ring-gray-600/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10' => ! in_array($subscriptionStatus, ['active', 'trialing', 'past_due', 'incomplete', 'unpaid'], true),
                        ])>{{ str($subscriptionStatus)->headline() }}</span>
                        <span class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/20">
                            {{ $planLabels->join(' + ') ?: 'No plan synchronized' }}
                        </span>
                    </div>

                    <h2 class="mt-3 text-lg font-semibold text-gray-950 dark:text-white">Billing and plan</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Purchased {{ $subscription->created_at?->format('M j, Y \a\t H:i') ?? 'at an unknown time' }}. Local subscription status is synchronized by Cashier webhooks.
                    </p>
                </div>

                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-o-credit-card" class="size-4" />
                    <span>{{ $paymentMethod }}</span>
                </div>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-primary-200 bg-primary-50/80 p-4 dark:border-primary-400/20 dark:bg-primary-400/10">
                    <p class="text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-300">Purchased plan</p>
                    <p class="mt-2 text-xl font-semibold tracking-tight text-primary-950 dark:text-primary-100">{{ $planLabels->join(' + ') ?: 'No plan synchronized' }}</p>
                    <p class="mt-1 text-xs text-primary-700/70 dark:text-primary-300/70">{{ $priceIds->count() }} synced Stripe {{ str('price')->plural($priceIds->count()) }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-white/10 dark:bg-white/[0.035]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Subscription type</p>
                    <p class="mt-2 text-xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ str($subscription->type)->headline() }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $subscription->quantity ?? 1 }} {{ $subscription->quantity === 1 ? 'seat' : 'seats' }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-white/10 dark:bg-white/[0.035]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Trial ends</p>
                    <p class="mt-2 text-xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $subscription->trial_ends_at?->format('M j, Y') ?? 'No trial' }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $subscription->trial_ends_at?->format('H:i') ?? '—' }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-white/10 dark:bg-white/[0.035]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Access ends</p>
                    <p class="mt-2 text-xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $subscription->ends_at?->format('M j, Y') ?? 'Renews' }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $subscription->ends_at?->format('H:i') ?? 'Automatically' }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-12">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-6 xl:col-span-7">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-300">
                    <x-filament::icon icon="heroicon-o-user" class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Customer</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $user instanceof \App\Models\User ? $user->full_name : 'Unknown player' }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">The app user associated with this Stripe subscription.</p>
                </div>
            </div>

            <dl class="mt-6 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Email</dt>
                    <dd class="mt-2 break-all text-sm font-medium text-gray-900 dark:text-white">{{ $user instanceof \App\Models\User ? $user->email : 'Not available' }}</dd>
                </div>
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Default payment method</dt>
                    <dd class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $paymentMethod }}</dd>
                </div>
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10 sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Stripe customer</dt>
                    <dd class="mt-2 break-all font-mono text-xs font-medium text-gray-800 dark:text-gray-200">{{ $user instanceof \App\Models\User && filled($user->stripe_id) ? $user->stripe_id : 'Not synchronized' }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-6 xl:col-span-5">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300">
                    <x-filament::icon icon="heroicon-o-finger-print" class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Subscription identity</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">Stripe references</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Useful when reconciling a support request in Stripe.</p>
                </div>
            </div>

            <dl class="mt-6 divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 dark:divide-white/10 dark:border-white/10">
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Stripe subscription</dt>
                    <dd class="mt-1.5 break-all font-mono text-xs font-medium text-gray-800 dark:text-gray-200">{{ $subscription->stripe_id }}</dd>
                </div>
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Latest local sync</dt>
                    <dd class="mt-1.5 text-sm font-medium text-gray-900 dark:text-white">{{ $subscription->updated_at?->format('M j, Y H:i:s') ?? 'Not available' }}</dd>
                </div>
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Stripe price IDs</dt>
                    <dd class="mt-1.5 flex flex-col gap-1.5">
                        @forelse ($priceIds as $priceId)
                            <code class="break-all text-xs font-medium text-gray-800 dark:text-gray-200">{{ $priceId }}</code>
                        @empty
                            <span class="text-sm text-gray-400 dark:text-gray-500">No price synchronized</span>
                        @endforelse
                    </dd>
                </div>
            </dl>
        </section>
    </div>

    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-success-50 text-success-600 dark:bg-success-400/10 dark:text-success-300">
                    <x-filament::icon icon="heroicon-o-document-currency-dollar" class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Stripe billing</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">Invoice history</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">The most recent 100 invoices are loaded live from Stripe when this page opens.</p>
                </div>
            </div>
            <span class="w-fit rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 ring-1 ring-inset ring-gray-600/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                {{ count($invoiceHistory['invoices']) }} {{ str('invoice')->plural(count($invoiceHistory['invoices'])) }}
            </span>
        </div>

        @if ($invoiceHistory['unavailable'] !== null)
            <div class="mt-6 rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-400/20 dark:bg-warning-400/10 dark:text-warning-200">
                {{ $invoiceHistory['unavailable'] }}
            </div>
        @elseif ($invoiceHistory['invoices'] === [])
            <div class="mt-6 rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-white/15 dark:text-gray-400">
                No invoice records are currently available for this subscription.
            </div>
        @else
            <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left dark:divide-white/10">
                        <thead class="bg-gray-50/80 dark:bg-white/[0.035]">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Invoice</th>
                                <th scope="col" class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Issued</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total</th>
                                <th scope="col" class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">Open invoice</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-gray-900">
                            @foreach ($invoiceHistory['invoices'] as $invoice)
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-mono text-xs font-medium text-gray-900 dark:text-white">{{ $invoice['number'] }}</p>
                                        <p class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $invoice['id'] }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{{ $invoice['date']->format('M j, Y') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ $invoice['total'] }}</td>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
                                            'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-300 dark:ring-success-400/20' => $invoice['status'] === 'paid',
                                            'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-300 dark:ring-warning-400/20' => in_array($invoice['status'], ['open', 'draft'], true),
                                            'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-300 dark:ring-danger-400/20' => in_array($invoice['status'], ['uncollectible', 'void'], true),
                                            'bg-gray-100 text-gray-700 ring-gray-600/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10' => ! in_array($invoice['status'], ['paid', 'open', 'draft', 'uncollectible', 'void'], true),
                                        ])>{{ str($invoice['status'])->headline() }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($invoice['hosted_invoice_url'] !== null)
                                            <a href="{{ $invoice['hosted_invoice_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-300">
                                                Open
                                                <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="size-3.5" />
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>
</div>
