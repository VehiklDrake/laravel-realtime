<?php

namespace App\Http\Controllers;

use App\Actions\Games\FindCurrentPlayer;
use App\Actions\Games\LeaveGame;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeaveGameController extends Controller
{
    public function __invoke(Game $game, Request $request, FindCurrentPlayer $currentPlayer, LeaveGame $leaveGame): RedirectResponse
    {
        $player = $currentPlayer->require($game, $request);
        $leaveGame->handle($game, $player);
        $currentPlayer->forget($game, $request);

        return to_route('home');
    }
}
