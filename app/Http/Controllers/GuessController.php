<?php

namespace App\Http\Controllers;

use App\Actions\Games\FindCurrentPlayer;
use App\Actions\Games\SubmitGuess;
use App\Http\Requests\SubmitGuessRequest;
use App\Models\Game;
use Illuminate\Http\JsonResponse;

class GuessController extends Controller
{
    public function __invoke(Game $game, SubmitGuessRequest $request, FindCurrentPlayer $currentPlayer, SubmitGuess $submitGuess): JsonResponse
    {
        $player = $currentPlayer->require($game, $request);

        return response()->json(
            $submitGuess->handle(
                $game,
                $player,
                $request->integer('round_id'),
                $request->string('message')->toString(),
            ),
        );
    }
}
