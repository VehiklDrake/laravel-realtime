<?php

namespace Database\Factories;

use App\Models\DrawingStroke;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DrawingStroke>
 */
class DrawingStrokeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'round_id' => Round::factory(),
            'player_id' => Player::factory(),
            'client_id' => fake()->uuid(),
            'from_x' => fake()->randomFloat(4, 0, 1),
            'from_y' => fake()->randomFloat(4, 0, 1),
            'to_x' => fake()->randomFloat(4, 0, 1),
            'to_y' => fake()->randomFloat(4, 0, 1),
            'color' => '#18181b',
            'size' => 6,
        ];
    }
}
