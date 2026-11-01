<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscordActivityRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscordActivityRoom>
 */
final class DiscordActivityRoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instance_id' => fake()->unique()->numerify('##################'),
            'channel_id' => fake()->numerify('##################'),
            'guild_id' => fake()->numerify('##################'),
        ];
    }
}
