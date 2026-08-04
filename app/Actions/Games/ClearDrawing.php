<?php

namespace App\Actions\Games;

use App\Enums\GameStatus;
use App\Events\CanvasCleared;
use App\Models\Game;
use App\Models\Player;
use App\Models\Round;

class ClearDrawing
{
    public function handle(Game $game, Player $player, int $roundId): void
    {
        abort_unless($game->status === GameStatus::Playing, 409, 'The game is not active.');

        $round = $game->activeRound();
        abort_unless($round instanceof Round, 409, 'There is no active round.');
        abort_unless($round->id === $roundId, 409, 'This clear belongs to a previous round.');
        abort_unless($round->drawer_id === $player->id, 403, 'Only the current drawer can clear the canvas.');
        abort_if($round->ended_at !== null || now()->greaterThanOrEqualTo($round->ends_at), 409, 'This round has ended.');

        $round->drawingStrokes()->delete();
        CanvasCleared::dispatch($round);
    }
}
