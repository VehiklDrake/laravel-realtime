<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Home', [
            'defaults' => [
                'rounds' => config('game.rounds'),
                'roundDuration' => config('game.round_duration'),
            ],
        ]);
    }
}
