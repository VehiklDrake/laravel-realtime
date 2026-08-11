<?php

use App\Http\Controllers\DrawingController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameStateController;
use App\Http\Controllers\GuessController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JoinGameController;
use App\Http\Controllers\LeaveGameController;
use App\Http\Controllers\ProgressGameController;
use App\Http\Controllers\StartGameController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::post('/games', [GameController::class, 'store'])->middleware('throttle:20,1')->name('games.store');
Route::post('/games/join', JoinGameController::class)->middleware('throttle:30,1')->name('games.join');

Route::prefix('/game/{game}')->name('games.')->group(function (): void {
    Route::get('/', [GameController::class, 'show'])->name('show');
    Route::get('/state', GameStateController::class)->middleware('throttle:120,1')->name('state');
    Route::post('/progress', ProgressGameController::class)->middleware('throttle:120,1')->name('progress');
    Route::post('/start', StartGameController::class)->middleware('throttle:10,1')->name('start');
    Route::post('/guesses', GuessController::class)->middleware('throttle:60,1')->name('guesses.store');
    Route::post('/drawing', [DrawingController::class, 'store'])->middleware('throttle:600,1')->name('drawing.store');
    Route::delete('/drawing', [DrawingController::class, 'destroy'])->middleware('throttle:30,1')->name('drawing.destroy');
    Route::delete('/players/current', LeaveGameController::class)->middleware('throttle:10,1')->name('players.destroy');
});
