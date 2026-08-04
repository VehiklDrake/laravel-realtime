<?php

namespace App\Http\Controllers;

use App\Actions\Games\FindCurrentPlayer;
use App\Actions\Games\GameSnapshot;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameStateController extends Controller
{
    public function __invoke(Game $game, Request $request, FindCurrentPlayer $currentPlayer, GameSnapshot $snapshot): JsonResponse
    {
        $player = $currentPlayer->require($game, $request);

        return response()->json($snapshot->handle($game, $player));
    }
}
