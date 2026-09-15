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
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('stripe_price_id')->nullable()->unique();
            $table->unsignedInteger('price_cents')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->json('features');
            $table->unsignedInteger('action_limit');
            $table->unsignedInteger('voice_minutes_limit');
            $table->string('accent', 16)->default('teal');
            $table->string('icon_key', 32)->default('footprints');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('subscription_entitlements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->timestampTz('period_start');
            $table->timestampTz('period_end');
            $table->unsignedInteger('action_limit');
            $table->unsignedInteger('actions_used')->default(0);
            $table->unsignedBigInteger('voice_milliseconds_limit');
            $table->unsignedBigInteger('voice_milliseconds_used')->default(0);
            $table->timestamps();

            $table->unique(['subscription_id', 'period_start'], 'subscription_entitlement_period_unique');
            $table->index(['user_id', 'period_start', 'period_end'], 'subscription_entitlement_user_period_index');
        });

        Schema::create('subscription_usage_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('subscription_entitlement_id')
                ->constrained('subscription_entitlements')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->foreignUlid('prompt_id')->nullable()->constrained('prompts')->nullOnDelete();
            $table->string('type', 16);
            $table->string('status', 16);
            $table->unsignedBigInteger('quantity');
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();

            $table->index(
                ['subscription_entitlement_id', 'status', 'expires_at'],
                'subscription_usage_event_expiry_index',
            );
            $table->index(['user_id', 'type', 'occurred_at'], 'subscription_usage_event_user_type_index');
        });

        $now = now();

        foreach ($this->initialPlans() as $plan) {
            DB::table('subscription_plans')->updateOrInsert(
                ['slug' => $plan['slug']],
                [
                    ...$plan,
                    'features' => json_encode($plan['features'], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_usage_events');
        Schema::dropIfExists('subscription_entitlements');
        Schema::dropIfExists('subscription_plans');
    }

    /**
     * @return array<int, array{
     *     name:string,
     *     slug:string,
     *     stripe_price_id:?string,
     *     price_cents:int,
     *     currency:string,
     *     tagline:string,
     *     description:string,
     *     features:array<int, string>,
     *     action_limit:int,
     *     voice_minutes_limit:int,
     *     accent:string,
     *     icon_key:string,
     *     is_featured:bool,
     *     is_active:bool,
     *     sort_order:int
     * }>
     */
    private function initialPlans(): array
    {
        return [
            [
                'name' => 'Adventurer Pass',
                'slug' => 'explorer',
                'stripe_price_id' => $this->stripePrice('explorer'),
                'price_cents' => 1490,
                'currency' => 'USD',
                'tagline' => 'Start your journey',
                'description' => 'Built for casual players stepping into interactive stories with standard actions and voice narration.',
                'features' => [
                    '225 Actions',
                    '135 Minutes of Voice Narration',
                    'Access to All Interactive Stories',
                    'Full Multiplayer Access',
                    '2–3 Full Solo Story Experiences',
                ],
                'action_limit' => 225,
                'voice_minutes_limit' => 135,
                'accent' => 'teal',
                'icon_key' => 'footprints',
                'is_featured' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Hero Pass',
                'slug' => 'adventurer',
                'stripe_price_id' => $this->stripePrice('adventurer'),
                'price_cents' => 2990,
                'currency' => 'USD',
                'tagline' => 'Go deeper',
                'description' => 'Best for solo adventurers and duos wanting steady voice immersion and private lobby access.',
                'features' => [
                    '450 Actions',
                    '270 Minutes of Voice Narration',
                    'Access to All Interactive Stories',
                    'Full Multiplayer Access',
                    '4–5 Full Solo Story Experiences',
                ],
                'action_limit' => 450,
                'voice_minutes_limit' => 270,
                'accent' => 'gold',
                'icon_key' => 'mountains',
                'is_featured' => false,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Mythic Pass',
                'slug' => 'chronicle',
                'stripe_price_id' => $this->stripePrice('chronicle'),
                'price_cents' => 3990,
                'currency' => 'USD',
                'tagline' => 'Live the story',
                'description' => 'Built for active role-players wanting deeper campaign arcs and frequent voice immersion.',
                'features' => [
                    '600 Actions',
                    '360 Minutes of Voice Narration',
                    'Access to All Interactive Stories',
                    'Full Multiplayer Access',
                    '5–6 Full Solo Story Experiences',
                    'Shared Token Pooling for Hosted Lobbies',
                ],
                'action_limit' => 600,
                'voice_minutes_limit' => 360,
                'accent' => 'teal',
                'icon_key' => 'crown',
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];
    }

    private function stripePrice(string $plan): ?string
    {
        $price = config("services.stripe.prices.{$plan}");

        return is_string($price) && $price !== '' ? $price : null;
    }
};
