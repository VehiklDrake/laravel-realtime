<?php

namespace App\Http\Controllers;

use App\Actions\Games\FindCurrentPlayer;
use App\Actions\Games\GameSnapshot;
use App\Actions\Games\StartGame;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StartGameController extends Controller
{
    public function __invoke(Game $game, Request $request, FindCurrentPlayer $currentPlayer, StartGame $startGame, GameSnapshot $snapshot): JsonResponse
    {
        $player = $currentPlayer->require($game, $request);
        $startedGame = $startGame->handle($game, $player);

        return response()->json($snapshot->handle($startedGame, $player->refresh()));
    }
}
