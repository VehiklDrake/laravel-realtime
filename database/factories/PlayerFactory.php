<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'name' => fake()->unique()->firstName(),
            'token' => fake()->uuid(),
            'score' => 0,
            'is_host' => false,
        ];
    }

    public function host(): static
    {
        return $this->state(fn () => ['is_host' => true]);
    }
}
