<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\Request;

class FindCurrentPlayer
{
    public function handle(Game $game, Request $request): ?Player
    {
        $token = $request->session()->get($this->sessionKey($game));

        if (! is_string($token)) {
            return null;
        }

        return $game->players()->where('token', $token)->first();
    }

    public function require(Game $game, Request $request): Player
    {
        return $this->handle($game, $request) ?? abort(403, 'Join this room before continuing.');
    }

    public function remember(Game $game, Player $player, Request $request): void
    {
        $request->session()->put($this->sessionKey($game), $player->token);
    }

    public function forget(Game $game, Request $request): void
    {
        $request->session()->forget($this->sessionKey($game));
    }

    private function sessionKey(Game $game): string
    {
        return "games.{$game->id}.player_token";
    }
}
