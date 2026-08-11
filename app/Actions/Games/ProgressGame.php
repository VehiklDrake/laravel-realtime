<?php

namespace App\Actions\Games;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Round;

class ProgressGame
{
    public function __construct(
        private FinishRound $finishRound,
        private StartNextRound $startNextRound,
    ) {}

    public function handle(Game $game): void
    {
        $game = $game->refresh();

        if ($game->status !== GameStatus::Playing) {
            return;
        }

        $round = $game->activeRound();

        if (! $round instanceof Round) {
            return;
        }

        if (! $round->ended_at && now()->greaterThanOrEqualTo($round->ends_at)) {
            $this->finishRound->handle($round, $round->ends_at);
            $round->refresh();
        }

        if (
            $round->ended_at
            && now()->greaterThanOrEqualTo(
                $round->ended_at->addSeconds(config('game.round_intermission_duration')),
            )
        ) {
            $this->startNextRound->handle($round);
        }
    }
}
