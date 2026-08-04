<?php

namespace App\Actions\Games;

use App\Enums\GameStatus;
use App\Events\PlayerJoined;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JoinGame
{
    public function handle(string $code, string $name, ?string $existingToken = null): Player
    {
        $game = Game::query()->where('code', $code)->first();

        if (! $game) {
            throw ValidationException::withMessages(['code' => 'We could not find that room.']);
        }

        if ($existingToken) {
            $existingPlayer = $game->players()->where('token', $existingToken)->first();

            if ($existingPlayer) {
                return $existingPlayer;
            }
        }

        $player = DB::transaction(function () use ($game, $name): Player {
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);

            if ($lockedGame->status !== GameStatus::Waiting) {
                throw ValidationException::withMessages(['code' => 'This game has already started.']);
            }

            if ($lockedGame->players()->count() >= config('game.max_players')) {
                throw ValidationException::withMessages(['code' => 'This room is full.']);
            }

            $nameTaken = $lockedGame->players()
                ->whereRaw('lower(name) = ?', [Str::lower($name)])
                ->exists();

            if ($nameTaken) {
                throw ValidationException::withMessages(['name' => 'That name is already taken in this room.']);
            }

            return $lockedGame->players()->create([
                'name' => $name,
                'token' => (string) Str::uuid(),
            ]);
        });

        PlayerJoined::dispatch($player);

        return $player;
    }
}
