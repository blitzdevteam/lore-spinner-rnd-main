<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
final class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Pass',
            'slug' => fake()->unique()->slug(2),
            'stripe_price_id' => 'price_'.fake()->unique()->bothify('????????????????'),
            'price_cents' => fake()->numberBetween(999, 9999),
            'currency' => 'USD',
            'tagline' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'features' => ['100 Actions', '60 Minutes of Voice Narration'],
            'action_limit' => fake()->numberBetween(100, 1000),
            'voice_minutes_limit' => fake()->numberBetween(60, 600),
            'accent' => fake()->randomElement(['teal', 'gold']),
            'bonus_label' => null,
            'action_hint' => null,
            'featured_label' => null,
            'icon_key' => fake()->randomElement(['book', 'books-plus', 'library', 'crown', 'star', 'people']),
            'is_featured' => false,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
