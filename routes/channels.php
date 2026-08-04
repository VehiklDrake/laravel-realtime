<?php

use App\Models\Player;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('game.{gameId}', function (Player $player, int $gameId): array|false {
    if ($player->game_id !== $gameId) {
        return false;
    }

    return [
        'id' => $player->id,
        'name' => $player->name,
    ];
});
