<?php

namespace App\Http\Middleware;

use App\Models\Player;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateGameBroadcast
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $gameId = Str::of($request->string('channel_name')->toString())
            ->after('presence-game.')
            ->toInteger();

        if ($gameId > 0) {
            $token = $request->session()->get("games.{$gameId}.player_token");
            $player = is_string($token)
                ? Player::query()->where('game_id', $gameId)->where('token', $token)->first()
                : null;

            $request->setUserResolver(fn (): ?Player => $player);
        }

        return $next($request);
    }
}
