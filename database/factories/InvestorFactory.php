<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Investor>
 */
final class InvestorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->withCompleteProfile()->state([
                'is_active' => true,
            ]),
            'access_enabled' => true,
            'notes' => fake()->optional()->sentence(),
            'access_granted_at' => now(),
            'access_blocked_at' => null,
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'access_enabled' => false,
            'access_blocked_at' => now(),
        ]);
    }
}
