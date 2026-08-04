<?php

namespace App\Actions\Games;

use App\Enums\GameStatus;
use App\Events\PlayerLeft;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

class LeaveGame
{
    public function handle(Game $game, Player $player): void
    {
        abort_unless($game->status === GameStatus::Waiting, 409, 'Players cannot leave after the game starts.');

        DB::transaction(function () use ($game, $player): void {
            $wasHost = $player->is_host;
            $playerId = $player->id;
            $playerName = $player->name;

            $player->delete();

            $remainingPlayers = $game->players();

            if (! $remainingPlayers->exists()) {
                $game->delete();
            } elseif ($wasHost) {
                $remainingPlayers->oldest('id')->firstOrFail()->update(['is_host' => true]);
            }

            PlayerLeft::dispatch($game->id, $playerId, $playerName);
        });
    }
}
