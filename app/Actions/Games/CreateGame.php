<?php

namespace App\Actions\Games;

use App\Enums\GameStatus;
use App\Events\PlayerJoined;
use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateGame
{
    public function handle(string $hostName): Game
    {
        $game = DB::transaction(function () use ($hostName): Game {
            $game = Game::query()->create([
                'code' => $this->uniqueCode(),
                'status' => GameStatus::Waiting,
                'total_rounds' => config('game.rounds'),
                'round_duration' => config('game.round_duration'),
            ]);

            $game->players()->create([
                'name' => $hostName,
                'token' => (string) Str::uuid(),
                'is_host' => true,
            ]);

            return $game;
        });

        $game->load('players');
        PlayerJoined::dispatch($game->players->sole());

        return $game;
    }

    private function uniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = collect(range(1, 6))
                ->map(fn (): string => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
        } while (Game::query()->where('code', $code)->exists());

        return $code;
    }
}
