<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Round>
 */
class RoundFactory extends Factory
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
            'drawer_id' => Player::factory(),
            'number' => 1,
            'word' => fake()->randomElement(config('game.words')),
            'started_at' => now(),
            'ends_at' => now()->addSeconds(config('game.round_duration')),
            'ended_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'started_at' => now(),
            'ends_at' => now()->addSeconds(config('game.round_duration')),
            'ended_at' => null,
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'started_at' => now()->subMinutes(2),
            'ends_at' => now()->subMinute(),
            'ended_at' => now()->subMinute(),
        ]);
    }
}
