<?php

namespace App\Actions\Games;

use App\Events\RoundStarted;
use App\Jobs\EndRound;
use App\Models\Game;
use App\Models\Round;
use Illuminate\Support\Arr;

class StartRound
{
    public function handle(Game $game, int $number): Round
    {
        $players = $game->players()->oldest('id')->get();
        $drawer = $players[($number - 1) % $players->count()];
        $startedAt = now();

        $round = $game->rounds()->create([
            'drawer_id' => $drawer->id,
            'number' => $number,
            'word' => Arr::random(config('game.words')),
            'started_at' => $startedAt,
            'ends_at' => $startedAt->addSeconds($game->round_duration),
        ]);

        $game->update(['current_round' => $number]);

        RoundStarted::dispatch($round->load('drawer'));
        EndRound::dispatch($round->id)->delay($round->ends_at)->afterCommit();

        return $round;
    }
}
