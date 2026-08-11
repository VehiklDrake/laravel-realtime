<?php

namespace App\Http\Controllers;

use App\Actions\Games\FindCurrentPlayer;
use App\Actions\Games\GameSnapshot;
use App\Actions\Games\ProgressGame;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressGameController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Game $game,
        Request $request,
        FindCurrentPlayer $currentPlayer,
        ProgressGame $progressGame,
        GameSnapshot $snapshot,
    ): JsonResponse {
        $player = $currentPlayer->require($game, $request);
        $progressGame->handle($game);

        return response()->json($snapshot->handle($game->refresh(), $player->refresh()));
    }
}
