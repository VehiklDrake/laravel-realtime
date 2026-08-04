<?php

namespace App\Actions\Games;

use App\Enums\GameStatus;
use App\Events\GameStarted;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartGame
{
    public function __construct(private StartRound $startRound) {}

    public function handle(Game $game, Player $player): Game
    {
        $startedGame = DB::transaction(function () use ($game, $player): Game {
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);

            abort_unless($player->is_host && $player->game_id === $lockedGame->id, 403);

            if ($lockedGame->status !== GameStatus::Waiting) {
                throw ValidationException::withMessages(['game' => 'This game has already started.']);
            }

            if ($lockedGame->players()->count() < config('game.min_players')) {
                throw ValidationException::withMessages(['players' => 'Invite at least one more player before starting.']);
            }

            $lockedGame->players()->update(['score' => 0]);
            $lockedGame->update(['status' => GameStatus::Playing]);
            $this->startRound->handle($lockedGame, 1);

            return $lockedGame;
        });

        GameStarted::dispatch($startedGame->refresh());

        return $startedGame;
    }
}
