<?php

namespace App\Http\Controllers;

use App\Actions\Games\ClearDrawing;
use App\Actions\Games\FindCurrentPlayer;
use App\Actions\Games\RecordDrawing;
use App\Http\Requests\ClearDrawingRequest;
use App\Http\Requests\StoreDrawingRequest;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DrawingController extends Controller
{
    public function store(Game $game, StoreDrawingRequest $request, FindCurrentPlayer $currentPlayer, RecordDrawing $recordDrawing): JsonResponse
    {
        $player = $currentPlayer->require($game, $request);
        $strokes = $recordDrawing->handle(
            $game,
            $player,
            $request->integer('round_id'),
            $request->validated('strokes'),
        );

        return response()->json(['recorded' => $strokes->count()]);
    }

    public function destroy(Game $game, ClearDrawingRequest $request, FindCurrentPlayer $currentPlayer, ClearDrawing $clearDrawing): Response
    {
        $clearDrawing->handle(
            $game,
            $currentPlayer->require($game, $request),
            $request->integer('round_id'),
        );

        return response()->noContent();
    }
}
