<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Actions\Investor\SyncInvestorPlanAccessAction;
use App\Enums\GenderEnum;
use App\Enums\UserType;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => UserType::User,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'username' => fake()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'gender' => fake()->randomElement(GenderEnum::values()),
            'bio' => fake()->paragraph(),
            'is_active' => fake()->boolean(),
            'welcomed_at' => now(),
        ];
    }

    /**
     * Mark the model has completed profile by setting username.
     */
    public function withCompleteProfile(): Factory
    {
        return $this->state(fn (array $attributes): array => [
            'username' => fake()->userName(),
        ]);
    }

    /**
     * Mark the model's email as verified.
     */
    public function markEmailAsVerified(): Factory
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => now(),
        ]);
    }

    public function investor(): static
    {
        $durationMonths = 1;
        $grantedAt = now();

        return $this->state(fn (array $attributes): array => [
            'type' => UserType::Investor,
            'is_active' => true,
            'subscription_plan_id' => $attributes['subscription_plan_id'] ?? SubscriptionPlan::factory(),
            'duration_months' => $durationMonths,
            'access_enabled' => true,
            'notes' => fake()->optional()->sentence(),
            'access_granted_at' => $grantedAt,
            'expires_at' => $grantedAt->copy()->addMonthsNoOverflow($durationMonths),
            'access_blocked_at' => null,
            'credentials_set_at' => now(),
        ])->afterCreating(function (User $user): void {
            if (! $user->isInvestor() || ! $user->access_enabled || $user->subscription_plan_id === null) {
                return;
            }

            app(SyncInvestorPlanAccessAction::class)->grant($user);
        });
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'access_enabled' => false,
            'access_blocked_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'access_granted_at' => now()->subMonthsNoOverflow(2),
            'expires_at' => now()->subDay(),
        ]);
    }

    public function pendingSetup(): static
    {
        return $this->state(fn (): array => [
            'credentials_set_at' => null,
            'email' => 'investor-'.fake()->unique()->bothify('????????').'@'.User::PENDING_EMAIL_DOMAIN,
            'email_verified_at' => null,
        ]);
    }
}
