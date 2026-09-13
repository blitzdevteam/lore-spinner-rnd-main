<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Subscription\SubscriptionAccessRequiredException;
use App\Exceptions\Subscription\SubscriptionUsageLimitExceededException;
use App\Models\Game;
use App\Models\MultiplayerLobby;
use App\Models\Prompt;
use App\Models\SubscriptionEntitlement;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUsageEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Subscription;

/**
 * Owns the application's allowance ledger. Cashier remains the source of truth
 * for whether a subscription is active; this service snapshots plan allowances
 * per Stripe billing period and atomically reserves player usage against them.
 */
final class SubscriptionEntitlementService
{
    private const int RESERVATION_MINUTES = 10;

    private const int VOICE_RESERVATION_WORDS_PER_MINUTE = 120;

    public function hasPlayAccess(User $user): bool
    {
        return $this->currentEntitlement($user) !== null;
    }

    /**
     * @return array{
     *     plan:array{name:string},
     *     actions:array{limit:int,used:int,remaining:int},
     *     voice:array{limit_minutes:int,used_milliseconds:int,remaining_milliseconds:int},
     *     period_ends_at:string
     * }|null
     */
    public function summary(User $user): ?array
    {
        $entitlement = $this->currentEntitlement($user);
        if ($entitlement === null) {
            return null;
        }

        $entitlement->loadMissing('plan');

        return [
            'plan' => [
                'name' => $entitlement->plan->name,
            ],
            'actions' => [
                'limit' => $entitlement->action_limit,
                'used' => $entitlement->actions_used,
                'remaining' => $entitlement->remainingActions(),
            ],
            'voice' => [
                'limit_minutes' => (int) ceil($entitlement->voice_milliseconds_limit / 60_000),
                'used_milliseconds' => $entitlement->voice_milliseconds_used,
                'remaining_milliseconds' => $entitlement->remainingVoiceMilliseconds(),
            ],
            'period_ends_at' => $entitlement->period_end->toIso8601String(),
        ];
    }

    public function shouldTrackUsageFor(Game $game): bool
    {
        return ! MultiplayerLobby::query()->where('game_id', $game->id)->exists();
    }

    public function reserveAction(User $user, Game $game, Prompt $sourcePrompt): SubscriptionUsageEvent
    {
        return DB::transaction(function () use ($user, $game, $sourcePrompt): SubscriptionUsageEvent {
            $entitlement = $this->lockedCurrentEntitlement($user);
            if ($entitlement === null) {
                throw new SubscriptionAccessRequiredException;
            }

            $idempotencyKey = "action:{$game->id}:{$sourcePrompt->id}";
            $existing = SubscriptionUsageEvent::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->status !== SubscriptionUsageEvent::STATUS_RELEASED) {
                return $existing;
            }

            $this->assertAvailable($entitlement, SubscriptionUsageEvent::TYPE_ACTION, 1);
            $this->incrementUsage($entitlement, SubscriptionUsageEvent::TYPE_ACTION, 1);

            if ($existing !== null) {
                $existing->update([
                    'subscription_entitlement_id' => $entitlement->id,
                    'user_id' => $user->id,
                    'game_id' => $game->id,
                    'prompt_id' => $sourcePrompt->id,
                    'type' => SubscriptionUsageEvent::TYPE_ACTION,
                    'status' => SubscriptionUsageEvent::STATUS_RESERVED,
                    'quantity' => 1,
                    'expires_at' => $this->reservationExpiry(),
                    'occurred_at' => now()->toImmutable()->utc(),
                ]);

                return $existing->fresh() ?? $existing;
            }

            return SubscriptionUsageEvent::query()->create([
                'subscription_entitlement_id' => $entitlement->id,
                'user_id' => $user->id,
                'game_id' => $game->id,
                'prompt_id' => $sourcePrompt->id,
                'type' => SubscriptionUsageEvent::TYPE_ACTION,
                'status' => SubscriptionUsageEvent::STATUS_RESERVED,
                'quantity' => 1,
                'idempotency_key' => $idempotencyKey,
                'expires_at' => $this->reservationExpiry(),
                'occurred_at' => now()->toImmutable()->utc(),
            ]);
        });
    }

    public function commitAction(string $reservationId): void
    {
        $this->commitReservation($reservationId, SubscriptionUsageEvent::TYPE_ACTION);
    }

    public function releaseAction(string $reservationId): void
    {
        $this->releaseReservation($reservationId, SubscriptionUsageEvent::TYPE_ACTION);
    }

    public function reserveVoice(
        User $user,
        Game $game,
        Prompt $prompt,
        string $text,
    ): SubscriptionUsageEvent {
        $estimatedMilliseconds = $this->estimatedVoiceMilliseconds($text);

        return DB::transaction(function () use ($user, $game, $prompt, $estimatedMilliseconds): SubscriptionUsageEvent {
            $entitlement = $this->lockedCurrentEntitlement($user);
            if ($entitlement === null) {
                throw new SubscriptionAccessRequiredException;
            }

            $this->assertAvailable($entitlement, SubscriptionUsageEvent::TYPE_VOICE, $estimatedMilliseconds);
            $this->incrementUsage($entitlement, SubscriptionUsageEvent::TYPE_VOICE, $estimatedMilliseconds);

            return SubscriptionUsageEvent::query()->create([
                'subscription_entitlement_id' => $entitlement->id,
                'user_id' => $user->id,
                'game_id' => $game->id,
                'prompt_id' => $prompt->id,
                'type' => SubscriptionUsageEvent::TYPE_VOICE,
                'status' => SubscriptionUsageEvent::STATUS_RESERVED,
                'quantity' => $estimatedMilliseconds,
                'expires_at' => $this->reservationExpiry(),
                'occurred_at' => now()->toImmutable()->utc(),
            ]);
        });
    }

    public function settleVoice(string $reservationId, int $actualMilliseconds): void
    {
        DB::transaction(function () use ($reservationId, $actualMilliseconds): void {
            $reservation = SubscriptionUsageEvent::query()->whereKey($reservationId)->lockForUpdate()->first();
            if ($reservation === null || $reservation->type !== SubscriptionUsageEvent::TYPE_VOICE) {
                return;
            }

            if ($reservation->status === SubscriptionUsageEvent::STATUS_CONSUMED) {
                return;
            }

            if ($reservation->status !== SubscriptionUsageEvent::STATUS_RESERVED) {
                throw new SubscriptionUsageLimitExceededException(SubscriptionUsageLimitExceededException::VOICE);
            }

            $entitlement = SubscriptionEntitlement::query()
                ->whereKey($reservation->subscription_entitlement_id)
                ->lockForUpdate()
                ->first();
            if ($entitlement === null) {
                throw new SubscriptionAccessRequiredException;
            }

            $reservedMilliseconds = $reservation->quantity;
            $actualMilliseconds = $actualMilliseconds > 0 ? $actualMilliseconds : $reservedMilliseconds;
            $additionalMilliseconds = max(0, $actualMilliseconds - $reservedMilliseconds);

            if ($additionalMilliseconds > 0) {
                $this->assertAvailable($entitlement, SubscriptionUsageEvent::TYPE_VOICE, $additionalMilliseconds);
                $this->incrementUsage($entitlement, SubscriptionUsageEvent::TYPE_VOICE, $additionalMilliseconds);
            }

            if ($actualMilliseconds < $reservedMilliseconds) {
                $this->decrementUsage(
                    $entitlement,
                    SubscriptionUsageEvent::TYPE_VOICE,
                    $reservedMilliseconds - $actualMilliseconds,
                );
            }

            $reservation->update([
                'status' => SubscriptionUsageEvent::STATUS_CONSUMED,
                'quantity' => $actualMilliseconds,
                'expires_at' => null,
            ]);
        });
    }

    public function releaseVoice(string $reservationId): void
    {
        $this->releaseReservation($reservationId, SubscriptionUsageEvent::TYPE_VOICE);
    }

    /**
     * Creates a period snapshot after Cashier has synchronized a Stripe
     * subscription webhook into the local subscriptions table.
     *
     * @param  array<string, mixed>  $payload
     */
    public function syncFromWebhook(array $payload): void
    {
        $eventType = data_get($payload, 'type');
        if (! in_array($eventType, ['customer.subscription.created', 'customer.subscription.updated'], true)) {
            return;
        }

        $stripeSubscription = data_get($payload, 'data.object');
        if (! is_array($stripeSubscription)) {
            return;
        }

        $userId = data_get($stripeSubscription, 'customer');
        if (! is_string($userId) || $userId === '') {
            return;
        }

        $user = User::query()->where('stripe_id', $userId)->first();
        if (! $user instanceof User) {
            return;
        }

        $this->synchronizeStripeSubscription($user, $stripeSubscription);
    }

    /**
     * Synchronize a verified Stripe subscription when the customer returns
     * from Checkout, so access does not depend on webhook delivery timing.
     *
     * @param  array<string, mixed>  $stripeSubscription
     */
    public function synchronizeStripeSubscription(User $user, array $stripeSubscription): void
    {
        $stripeSubscriptionId = data_get($stripeSubscription, 'id');
        $stripeCustomerId = data_get($stripeSubscription, 'customer');
        $items = data_get($stripeSubscription, 'items.data');

        if (
            ! is_string($stripeSubscriptionId)
            || $stripeSubscriptionId === ''
            || ! is_string($stripeCustomerId)
            || $stripeCustomerId !== $user->stripe_id
            || ! is_array($items)
            || $items === []
        ) {
            return;
        }

        $firstItem = $items[0] ?? null;
        $firstPriceId = data_get($firstItem, 'price.id');
        $status = data_get($stripeSubscription, 'status');

        if (! is_array($firstItem) || ! is_string($firstPriceId) || $firstPriceId === '' || ! is_string($status)) {
            return;
        }

        $isSinglePrice = count($items) === 1;
        $trialEndsAt = $this->stripeTimestamp(data_get($stripeSubscription, 'trial_end'));
        $subscription = $user->subscriptions()->updateOrCreate(
            ['stripe_id' => $stripeSubscriptionId],
            [
                'type' => data_get($stripeSubscription, 'metadata.type')
                    ?? data_get($stripeSubscription, 'metadata.name')
                    ?? 'default',
                'stripe_status' => $status,
                'stripe_price' => $isSinglePrice ? $firstPriceId : null,
                'quantity' => $isSinglePrice ? data_get($firstItem, 'quantity') : null,
                'trial_ends_at' => $trialEndsAt,
                'ends_at' => $this->subscriptionEndsAt($stripeSubscription),
            ],
        );

        foreach ($items as $item) {
            $itemId = data_get($item, 'id');
            $itemPriceId = data_get($item, 'price.id');
            $productId = data_get($item, 'price.product');

            if (
                ! is_array($item)
                || ! is_string($itemId)
                || ! is_string($itemPriceId)
                || ! is_string($productId)
            ) {
                continue;
            }

            $subscription->items()->updateOrCreate(
                ['stripe_id' => $itemId],
                [
                    'stripe_product' => $productId,
                    'stripe_price' => $itemPriceId,
                    'quantity' => data_get($item, 'quantity'),
                ],
            );
        }

        if ($user->trial_ends_at !== null) {
            $user->forceFill(['trial_ends_at' => null])->save();
        }

        if ($subscription->type !== 'default') {
            return;
        }

        $this->syncEntitlement($subscription, $stripeSubscription);
    }

    /**
     * @param  array<string, mixed>  $stripeSubscription
     */
    private function syncEntitlement(Subscription $subscription, array $stripeSubscription): void
    {
        $priceId = $this->priceIdFromStripeSubscription($stripeSubscription) ?? $this->priceIdFor($subscription);
        if ($priceId === null) {
            return;
        }

        $plan = SubscriptionPlan::query()->where('stripe_price_id', $priceId)->first();
        if ($plan === null) {
            return;
        }

        $periodStart = $this->stripeTimestamp(
            $stripeSubscription['current_period_start'] ?? data_get($stripeSubscription, 'items.data.0.current_period_start'),
        );
        $periodEnd = $this->stripeTimestamp(
            $stripeSubscription['current_period_end'] ?? data_get($stripeSubscription, 'items.data.0.current_period_end'),
        );
        if ($periodStart === null || $periodEnd === null || $periodEnd->lessThanOrEqualTo($periodStart)) {
            return;
        }

        SubscriptionEntitlement::query()->firstOrCreate(
            [
                'subscription_id' => $subscription->id,
                'period_start' => $periodStart,
            ],
            [
                'user_id' => $subscription->user_id,
                'subscription_plan_id' => $plan->id,
                'period_end' => $periodEnd,
                'action_limit' => $plan->action_limit,
                'voice_milliseconds_limit' => $plan->voiceMillisecondsLimit(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $stripeSubscription
     */
    private function subscriptionEndsAt(array $stripeSubscription): ?CarbonImmutable
    {
        if (data_get($stripeSubscription, 'cancel_at_period_end') === true) {
            return $this->stripeTimestamp(
                $stripeSubscription['current_period_end'] ?? data_get($stripeSubscription, 'items.data.0.current_period_end'),
            );
        }

        return $this->stripeTimestamp(
            $stripeSubscription['cancel_at'] ?? data_get($stripeSubscription, 'canceled_at'),
        );
    }

    private function currentEntitlement(User $user): ?SubscriptionEntitlement
    {
        $subscription = $this->activeSubscriptionFor($user);
        if ($subscription === null) {
            return null;
        }

        $now = now()->toImmutable()->utc();
        $entitlement = $this->entitlementCovering($subscription, $now);

        return $entitlement ?? $this->createFallbackEntitlement($user, $subscription, $now);
    }

    private function lockedCurrentEntitlement(User $user): ?SubscriptionEntitlement
    {
        $entitlement = $this->currentEntitlement($user);
        if ($entitlement === null) {
            return null;
        }

        $lockedEntitlement = SubscriptionEntitlement::query()
            ->whereKey($entitlement->id)
            ->lockForUpdate()
            ->first();
        if ($lockedEntitlement === null) {
            return null;
        }

        $this->releaseExpiredReservations($lockedEntitlement);

        return $lockedEntitlement->fresh() ?? $lockedEntitlement;
    }

    private function activeSubscriptionFor(User $user): ?Subscription
    {
        if (! $user->subscribed('default')) {
            return null;
        }

        $subscription = $user->subscription('default');

        return $subscription instanceof Subscription ? $subscription : null;
    }

    private function entitlementCovering(Subscription $subscription, CarbonImmutable $at): ?SubscriptionEntitlement
    {
        return SubscriptionEntitlement::query()
            ->with('plan')
            ->where('subscription_id', $subscription->id)
            ->where('period_start', '<=', $at)
            ->where('period_end', '>', $at)
            ->orderByDesc('period_start')
            ->first();
    }

    private function createFallbackEntitlement(
        User $user,
        Subscription $subscription,
        CarbonImmutable $now,
    ): ?SubscriptionEntitlement {
        $plan = $this->planForSubscription($subscription);
        if ($plan === null) {
            return null;
        }

        [$periodStart, $periodEnd] = $this->fallbackPeriod($subscription, $now);
        if ($periodEnd->lessThanOrEqualTo($periodStart)) {
            return null;
        }

        return SubscriptionEntitlement::query()->firstOrCreate(
            [
                'subscription_id' => $subscription->id,
                'period_start' => $periodStart,
            ],
            [
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'period_end' => $periodEnd,
                'action_limit' => $plan->action_limit,
                'voice_milliseconds_limit' => $plan->voiceMillisecondsLimit(),
            ],
        );
    }

    /**
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    private function fallbackPeriod(Subscription $subscription, CarbonImmutable $now): array
    {
        $periodStart = $subscription->created_at?->toImmutable()->utc() ?? $now;
        $periodEnd = $periodStart->addMonthNoOverflow();

        while ($periodEnd->lessThanOrEqualTo($now)) {
            $periodStart = $periodEnd;
            $periodEnd = $periodStart->addMonthNoOverflow();
        }

        $accessEndsAt = $subscription->ends_at?->toImmutable()->utc();
        if ($accessEndsAt !== null && $accessEndsAt->lessThan($periodEnd)) {
            $periodEnd = $accessEndsAt;
        }

        return [$periodStart, $periodEnd];
    }

    private function planForSubscription(Subscription $subscription): ?SubscriptionPlan
    {
        $priceId = $this->priceIdFor($subscription);

        return $priceId === null
            ? null
            : SubscriptionPlan::query()->where('stripe_price_id', $priceId)->first();
    }

    private function priceIdFor(Subscription $subscription): ?string
    {
        if (is_string($subscription->stripe_price) && $subscription->stripe_price !== '') {
            return $subscription->stripe_price;
        }

        $priceId = $subscription->items()->value('stripe_price');

        return is_string($priceId) && $priceId !== '' ? $priceId : null;
    }

    /**
     * @param  array<string, mixed>  $stripeSubscription
     */
    private function priceIdFromStripeSubscription(array $stripeSubscription): ?string
    {
        $priceId = data_get($stripeSubscription, 'items.data.0.price.id');

        return is_string($priceId) && $priceId !== '' ? $priceId : null;
    }

    private function stripeTimestamp(mixed $timestamp): ?CarbonImmutable
    {
        return is_int($timestamp) || (is_string($timestamp) && ctype_digit($timestamp))
            ? CarbonImmutable::createFromTimestampUTC((int) $timestamp)
            : null;
    }

    private function assertAvailable(
        SubscriptionEntitlement $entitlement,
        string $type,
        int $quantity,
    ): void {
        $remaining = $type === SubscriptionUsageEvent::TYPE_ACTION
            ? $entitlement->remainingActions()
            : $entitlement->remainingVoiceMilliseconds();

        if ($quantity > $remaining) {
            throw new SubscriptionUsageLimitExceededException(
                $type === SubscriptionUsageEvent::TYPE_ACTION
                    ? SubscriptionUsageLimitExceededException::ACTIONS
                    : SubscriptionUsageLimitExceededException::VOICE,
            );
        }
    }

    private function incrementUsage(SubscriptionEntitlement $entitlement, string $type, int $quantity): void
    {
        $column = $type === SubscriptionUsageEvent::TYPE_ACTION
            ? 'actions_used'
            : 'voice_milliseconds_used';

        $entitlement->increment($column, $quantity);
        $entitlement->refresh();
    }

    private function decrementUsage(SubscriptionEntitlement $entitlement, string $type, int $quantity): void
    {
        if ($quantity === 0) {
            return;
        }

        $column = $type === SubscriptionUsageEvent::TYPE_ACTION
            ? 'actions_used'
            : 'voice_milliseconds_used';
        $current = (int) $entitlement->getAttribute($column);

        $entitlement->update([$column => max(0, $current - $quantity)]);
        $entitlement->refresh();
    }

    private function commitReservation(string $reservationId, string $type): void
    {
        DB::transaction(function () use ($reservationId, $type): void {
            $reservation = SubscriptionUsageEvent::query()->whereKey($reservationId)->lockForUpdate()->first();
            if ($reservation === null || $reservation->type !== $type) {
                return;
            }

            if ($reservation->status === SubscriptionUsageEvent::STATUS_RESERVED) {
                $reservation->update([
                    'status' => SubscriptionUsageEvent::STATUS_CONSUMED,
                    'expires_at' => null,
                ]);
            }
        });
    }

    private function releaseReservation(string $reservationId, string $type): void
    {
        DB::transaction(function () use ($reservationId, $type): void {
            $reservation = SubscriptionUsageEvent::query()->whereKey($reservationId)->lockForUpdate()->first();
            if (
                $reservation === null
                || $reservation->type !== $type
                || $reservation->status !== SubscriptionUsageEvent::STATUS_RESERVED
            ) {
                return;
            }

            $entitlement = SubscriptionEntitlement::query()
                ->whereKey($reservation->subscription_entitlement_id)
                ->lockForUpdate()
                ->first();
            if ($entitlement !== null) {
                $this->decrementUsage($entitlement, $type, $reservation->quantity);
            }

            $reservation->update([
                'status' => SubscriptionUsageEvent::STATUS_RELEASED,
                'expires_at' => null,
            ]);
        });
    }

    private function releaseExpiredReservations(SubscriptionEntitlement $entitlement): void
    {
        $expiredReservations = SubscriptionUsageEvent::query()
            ->where('subscription_entitlement_id', $entitlement->id)
            ->where('status', SubscriptionUsageEvent::STATUS_RESERVED)
            ->where('expires_at', '<=', now())
            ->lockForUpdate()
            ->get();

        if ($expiredReservations->isEmpty()) {
            return;
        }

        $releasedActions = $expiredReservations
            ->where('type', SubscriptionUsageEvent::TYPE_ACTION)
            ->sum('quantity');
        $releasedVoiceMilliseconds = $expiredReservations
            ->where('type', SubscriptionUsageEvent::TYPE_VOICE)
            ->sum('quantity');

        $entitlement->update([
            'actions_used' => max(0, $entitlement->actions_used - $releasedActions),
            'voice_milliseconds_used' => max(0, $entitlement->voice_milliseconds_used - $releasedVoiceMilliseconds),
        ]);

        foreach ($expiredReservations as $reservation) {
            $reservation->update([
                'status' => SubscriptionUsageEvent::STATUS_RELEASED,
                'expires_at' => null,
            ]);
        }
    }

    private function reservationExpiry(): CarbonImmutable
    {
        return now()->toImmutable()->utc()->addMinutes(self::RESERVATION_MINUTES);
    }

    private function estimatedVoiceMilliseconds(string $text): int
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $minutes = count($words) / self::VOICE_RESERVATION_WORDS_PER_MINUTE;

        return max(1_000, (int) ceil($minutes * 60_000));
    }
}
