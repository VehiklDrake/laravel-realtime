<?php

namespace App\Http\Controllers;

use App\Actions\Games\CreateGame;
use App\Actions\Games\FindCurrentPlayer;
use App\Actions\Games\GameSnapshot;
use App\Http\Requests\CreateGameRequest;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    public function store(CreateGameRequest $request, CreateGame $createGame, FindCurrentPlayer $currentPlayer): RedirectResponse
    {
        $game = $createGame->handle($request->string('name')->toString());
        $currentPlayer->remember($game, $game->players->sole(), $request);

        return to_route('games.show', $game);
    }

    public function show(Game $game, Request $request, FindCurrentPlayer $currentPlayer, GameSnapshot $snapshot): Response
    {
        $player = $currentPlayer->handle($game, $request);

        if (! $player) {
            return Inertia::render('Games/Join', ['code' => $game->code]);
        }

        return Inertia::render('Games/Room', $snapshot->handle($game, $player));
    }
}
