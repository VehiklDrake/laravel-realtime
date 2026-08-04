<?php

namespace Database\Factories;

use App\Models\Guess;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guess>
 */
class GuessFactory extends Factory
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
            'message' => fake()->words(3, true),
            'is_correct' => false,
            'points' => 0,
        ];
    }

    public function correct(int $points = 100): static
    {
        return $this->state(fn () => [
            'is_correct' => true,
            'points' => $points,
        ]);
    }
}
