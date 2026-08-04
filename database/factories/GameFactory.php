<?php

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->regexify('[A-HJ-NP-Z2-9]{6}'),
            'status' => GameStatus::Waiting,
            'current_round' => 0,
            'total_rounds' => config('game.rounds'),
            'round_duration' => config('game.round_duration'),
        ];
    }

    public function playing(): static
    {
        return $this->state(fn () => [
            'status' => GameStatus::Playing,
            'current_round' => 1,
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn () => ['status' => GameStatus::Finished]);
    }
}
