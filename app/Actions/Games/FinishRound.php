<?php

namespace App\Actions\Games;

use App\Enums\GameStatus;
use App\Events\RoundEnded;
use App\Jobs\AdvanceRound;
use App\Models\Game;
use App\Models\Round;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class FinishRound
{
    public function handle(Round $round, ?DateTimeInterface $endedAt = null): void
    {
        $finishedRound = DB::transaction(function () use ($round, $endedAt): ?Round {
            $lockedRound = Round::query()->with('game')->lockForUpdate()->find($round->id);

            if (! $lockedRound || $lockedRound->ended_at !== null) {
                return null;
            }

            $game = Game::query()->lockForUpdate()->findOrFail($lockedRound->game_id);

            if ($game->status !== GameStatus::Playing || $game->current_round !== $lockedRound->number) {
                return null;
            }

            $resolvedEndedAt = $endedAt && $endedAt < now() ? $endedAt : now();
            $lockedRound->update(['ended_at' => $resolvedEndedAt]);

            return $lockedRound->refresh();
        });

        if (! $finishedRound) {
            return;
        }

        $game = $finishedRound->game;

        RoundEnded::dispatch($finishedRound, $game->players()->orderByDesc('score')->get());
        AdvanceRound::dispatch($finishedRound->id)
            ->delay($finishedRound->ended_at->addSeconds(config('game.round_intermission_duration')))
            ->afterCommit();
    }
}
