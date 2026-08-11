<?php

namespace App\Actions\Games;

use App\Enums\GameStatus;
use App\Events\DrawingStrokes;
use App\Models\DrawingStroke;
use App\Models\Game;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Database\Eloquent\Collection;

class RecordDrawing
{
    /**
     * @param  list<array{client_id: string, from_x: float|int, from_y: float|int, to_x: float|int, to_y: float|int, color: string, size: int}>  $strokes
     * @return Collection<int, DrawingStroke>
     */
    public function handle(Game $game, Player $player, int $roundId, array $strokes): Collection
    {
        abort_unless($game->status === GameStatus::Playing, 409, 'The game is not active.');

        $round = $game->activeRound();
        abort_unless($round instanceof Round, 409, 'There is no active round.');
        abort_unless($round->id === $roundId, 409, 'This drawing belongs to a previous round.');
        abort_unless($round->drawer_id === $player->id, 403, 'Only the current drawer can draw.');
        abort_if($round->ended_at !== null || now()->greaterThanOrEqualTo($round->ends_at), 409, 'This round has ended.');

        $recordedStrokes = new Collection;

        foreach ($strokes as $stroke) {
            $recordedStroke = $round->drawingStrokes()->firstOrCreate(
                ['client_id' => $stroke['client_id']],
                [
                    ...$stroke,
                    'player_id' => $player->id,
                ],
            );

            $recordedStrokes->push($recordedStroke);
        }

        DrawingStrokes::dispatch($recordedStrokes);

        return $recordedStrokes;
    }
}
