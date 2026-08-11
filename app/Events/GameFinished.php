<?php

namespace App\Events;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameFinished implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Game $game) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("game.{$this->game->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'GameFinished';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'players' => $this->game->players()->orderByDesc('score')->get()->map(fn (Player $player): array => [
                'id' => $player->id,
                'name' => $player->name,
                'score' => $player->score,
            ])->values()->all(),
        ];
    }
}
