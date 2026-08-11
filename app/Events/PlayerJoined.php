<?php

namespace App\Events;

use App\Models\Player;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerJoined implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Player $player) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("game.{$this->player->game_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PlayerJoined';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'player' => [
                'id' => $this->player->id,
                'name' => $this->player->name,
                'score' => $this->player->score,
                'is_host' => $this->player->is_host,
            ],
        ];
    }
}
