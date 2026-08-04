<?php

namespace App\Actions\Games;

use App\Enums\GameStatus;
use App\Events\GameFinished;
use App\Events\RoundEnded;
use App\Models\Game;
use App\Models\Round;
use Illuminate\Support\Facades\DB;

class FinishRound
{
    public function __construct(private StartRound $startRound) {}

    public function handle(Round $round): void
    {
        $result = DB::transaction(function () use ($round): ?array {
            $lockedRound = Round::query()->with('game')->lockForUpdate()->find($round->id);

            if (! $lockedRound || $lockedRound->ended_at !== null) {
                return null;
            }

            $game = Game::query()->lockForUpdate()->findOrFail($lockedRound->game_id);

            if ($game->status !== GameStatus::Playing || $game->current_round !== $lockedRound->number) {
                return null;
            }

            $lockedRound->update(['ended_at' => now()]);

            if ($lockedRound->number >= $game->total_rounds) {
                $game->update(['status' => GameStatus::Finished]);

                return ['round' => $lockedRound, 'game' => $game, 'finished' => true];
            }

            return ['round' => $lockedRound, 'game' => $game, 'finished' => false];
        });

        if (! $result) {
            return;
        }

        /** @var Round $finishedRound */
        $finishedRound = $result['round'];
        /** @var Game $game */
        $game = $result['game'];

        RoundEnded::dispatch($finishedRound, $game->players()->orderByDesc('score')->get());

        if ($result['finished']) {
            GameFinished::dispatch($game->refresh());

            return;
        }

        $this->startRound->handle($game->refresh(), $finishedRound->number + 1);
    }
}
