<?php

namespace App\Actions\Games;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Guess;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Support\Str;

class GameSnapshot
{
    /** @return array<string, mixed> */
    public function handle(Game $game, Player $currentPlayer): array
    {
        $game->load([
            'players' => fn ($query) => $query->orderByDesc('score')->oldest('id'),
        ]);

        $round = $game->activeRound();

        if ($round) {
            $round->load([
                'drawer',
                'drawingStrokes' => fn ($query) => $query->oldest('id'),
            ]);
        }

        return [
            'game' => [
                'id' => $game->id,
                'code' => $game->code,
                'status' => $game->status->value,
                'current_round' => $game->current_round,
                'total_rounds' => $game->total_rounds,
                'round_duration' => $game->round_duration,
                'players' => $game->players->map(fn (Player $player): array => [
                    'id' => $player->id,
                    'name' => $player->name,
                    'score' => $player->score,
                    'is_host' => $player->is_host,
                ])->values()->all(),
            ],
            'player' => [
                'id' => $currentPlayer->id,
                'name' => $currentPlayer->name,
                'score' => $currentPlayer->score,
                'is_host' => $currentPlayer->is_host,
            ],
            'round' => $round ? $this->round($game, $round, $currentPlayer) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function round(Game $game, Round $round, Player $currentPlayer): array
    {
        $canSeeWord = $round->drawer_id === $currentPlayer->id
            || $round->ended_at !== null
            || $game->status === GameStatus::Finished;

        return [
            'id' => $round->id,
            'number' => $round->number,
            'drawer_id' => $round->drawer_id,
            'drawer_name' => $round->drawer->name,
            'started_at' => $round->started_at->toISOString(),
            'ends_at' => $round->ends_at->toISOString(),
            'ended_at' => $round->ended_at?->toISOString(),
            'word' => $canSeeWord ? $round->word : null,
            'masked_word' => $canSeeWord ? null : Str::of($round->word)
                ->replaceMatches('/[^\s]/u', '_')
                ->toString(),
            'strokes' => $round->drawingStrokes->map(fn ($stroke): array => [
                'id' => $stroke->id,
                'from_x' => $stroke->from_x,
                'from_y' => $stroke->from_y,
                'to_x' => $stroke->to_x,
                'to_y' => $stroke->to_y,
                'color' => $stroke->color,
                'size' => $stroke->size,
            ])->all(),
            'messages' => $game->rounds()
                ->with(['guesses' => fn ($query) => $query->with('player')->oldest('id')])
                ->oldest('number')
                ->get()
                ->flatMap(fn (Round $messageRound) => $messageRound->guesses->map(
                    fn (Guess $guess): array => $this->message($messageRound, $guess),
                ))
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function message(Round $round, Guess $guess): array
    {
        return [
            'id' => $guess->id,
            'round_id' => $round->id,
            'round_number' => $round->number,
            'player_id' => $guess->player_id,
            'player' => $guess->player->name,
            'message' => $guess->is_correct ? 'guessed correctly!' : $guess->message,
            'is_correct' => $guess->is_correct,
        ];
    }
}
