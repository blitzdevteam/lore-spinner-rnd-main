<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ComingSoonStory\ComingSoonStorySectionEnum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComingSoonStory>
 */
final class ComingSoonStoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'section' => fake()->randomElement(ComingSoonStorySectionEnum::values()),
            'teaser' => fake()->paragraph(),
            'category' => fake()->optional()->randomElement([
                'Mystery',
                'Fantasy Adventure',
                'Gothic Horror',
                'Science Fiction',
            ]),
            'rating' => fake()->optional()->randomElement(['Everyone', 'Teen']),
            'author' => fake()->optional()->name(),
            'series_label' => fake()->optional()->randomElement(['Lore Spinner Classic', 'Lore Spinner Original']),
            'image_position' => fake()->optional()->randomElement([
                'object-center',
                'object-top',
                'object-[60%_top] md:object-top',
            ]),
            'position' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
