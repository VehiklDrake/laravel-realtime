<?php

namespace App\Http\Controllers;

use App\Actions\Games\FindCurrentPlayer;
use App\Actions\Games\JoinGame;
use App\Http\Requests\JoinGameRequest;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;

class JoinGameController extends Controller
{
    public function __invoke(JoinGameRequest $request, JoinGame $joinGame, FindCurrentPlayer $currentPlayer): RedirectResponse
    {
        $game = Game::query()->where('code', $request->string('code')->toString())->first();
        $existingToken = $game
            ? $request->session()->get("games.{$game->id}.player_token")
            : null;

        $player = $joinGame->handle(
            $request->string('code')->toString(),
            $request->string('name')->toString(),
            is_string($existingToken) ? $existingToken : null,
        );

        $currentPlayer->remember($player->game, $player, $request);

        return to_route('games.show', $player->game);
    }
}
