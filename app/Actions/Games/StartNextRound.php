<?php

namespace App\Actions\Games;

use App\Enums\GameStatus;
use App\Events\GameFinished;
use App\Models\Game;
use App\Models\Round;
use Illuminate\Support\Facades\DB;

class StartNextRound
{
    public function __construct(private StartRound $startRound) {}

    public function handle(Round $round): void
    {
        $result = DB::transaction(function () use ($round): ?array {
            $lockedRound = Round::query()->lockForUpdate()->find($round->id);

            if (! $lockedRound || ! $lockedRound->ended_at) {
                return null;
            }

            $game = Game::query()->lockForUpdate()->findOrFail($lockedRound->game_id);
            $intermissionEndsAt = $lockedRound->ended_at->addSeconds(config('game.round_intermission_duration'));

            if (
                $game->status !== GameStatus::Playing
                || $game->current_round !== $lockedRound->number
                || now()->isBefore($intermissionEndsAt)
            ) {
                return null;
            }

            if ($lockedRound->number >= $game->total_rounds) {
                $game->update(['status' => GameStatus::Finished]);

                return ['game' => $game->refresh(), 'finished' => true];
            }

            $this->startRound->handle($game, $lockedRound->number + 1);

            return ['game' => $game->refresh(), 'finished' => false];
        });

        if ($result && $result['finished']) {
            GameFinished::dispatch($result['game']);
        }
    }
}
