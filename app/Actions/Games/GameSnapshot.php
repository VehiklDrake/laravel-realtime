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
                'guesses' => fn ($query) => $query->with('player')->oldest('id'),
            ]);
        }

        return [
            'server_time' => now()->toISOString(),
            'game' => [
                'id' => $game->id,
                'code' => $game->code,
                'status' => $game->status->value,
                'current_round' => $game->current_round,
                'total_rounds' => $game->total_rounds,
                'round_duration' => $game->round_duration,
                'round_intermission_duration' => config('game.round_intermission_duration'),
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
            'result' => $this->roundResult($round),
            'word' => $canSeeWord ? $round->word : null,
            'masked_word' => $canSeeWord ? null : Str::of($round->word)
                ->replaceMatches('/[^\s]/u', '_')
                ->toString(),
            'strokes' => $round->drawingStrokes->map(fn ($stroke): array => [
                'id' => $stroke->id,
                'client_id' => $stroke->client_id,
                'from_x' => $stroke->from_x,
                'from_y' => $stroke->from_y,
                'to_x' => $stroke->to_x,
                'to_y' => $stroke->to_y,
                'color' => $stroke->color,
                'size' => $stroke->size,
            ])->all(),
            'messages' => $round->guesses
                ->map(fn (Guess $guess): array => $this->message($round, $guess))
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function roundResult(Round $round): ?array
    {
        if (! $round->ended_at) {
            return null;
        }

        $correctGuesses = $round->guesses
            ->where('is_correct', true)
            ->map(function (Guess $correctGuess) use ($round): array {
                $guessCount = $round->guesses
                    ->where('player_id', $correctGuess->player_id)
                    ->where('id', '<=', $correctGuess->id)
                    ->count();

                return [
                    'player_id' => $correctGuess->player_id,
                    'player' => $correctGuess->player->name,
                    'seconds' => (int) $round->started_at->diffInSeconds($correctGuess->created_at),
                    'guess_count' => $guessCount,
                ];
            })
            ->values();

        return [
            'reason' => $correctGuesses->isEmpty() ? 'timeout' : 'correct',
            'intermission_ends_at' => $round->ended_at
                ->addSeconds(config('game.round_intermission_duration'))
                ->toISOString(),
            'correct_guesses' => $correctGuesses->all(),
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
