<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_entitlements', function (Blueprint $table): void {
            $table->foreignId('subscription_id')->nullable()->change();
            $table->timestampTz('period_end')->nullable()->change();
            $table->boolean('is_one_time')->default(false);
            $table->string('one_time_key')->nullable()->unique();
        });

        $now = now();

        DB::table('subscription_plans')->updateOrInsert(
            ['slug' => 'free'],
            [
                'name' => 'Free Pass',
                'stripe_price_id' => null,
                'price_cents' => 0,
                'currency' => 'USD',
                'tagline' => 'Take your first step',
                'description' => 'A one-time starter allowance for trying interactive stories before choosing a subscription.',
                'features' => json_encode([
                    '20 Actions',
                    '10 Minutes of Voice Narration',
                    'One-time allowance per account',
                ], JSON_THROW_ON_ERROR),
                'action_limit' => 20,
                'voice_minutes_limit' => 10,
                'accent' => 'teal',
                'icon_key' => 'footprints',
                'is_featured' => false,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        $freePlanId = DB::table('subscription_plans')
            ->where('slug', 'free')
            ->value('id');

        if ($freePlanId !== null) {
            $freeEntitlementIds = DB::table('subscription_entitlements')
                ->where('subscription_plan_id', $freePlanId)
                ->pluck('id');

            DB::table('subscription_usage_events')
                ->whereIn('subscription_entitlement_id', $freeEntitlementIds)
                ->delete();

            DB::table('subscription_entitlements')
                ->where('subscription_plan_id', $freePlanId)
                ->delete();

            DB::table('subscription_plans')
                ->where('id', $freePlanId)
                ->delete();
        }

        Schema::table('subscription_entitlements', function (Blueprint $table): void {
            $table->dropUnique('subscription_entitlements_one_time_key_unique');
            $table->dropColumn(['is_one_time', 'one_time_key']);
            $table->foreignId('subscription_id')->nullable(false)->change();
            $table->timestampTz('period_end')->nullable(false)->change();
        });
    }
};
