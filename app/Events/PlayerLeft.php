<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerLeft implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public int $gameId, public int $playerId, public string $name) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("game.{$this->gameId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PlayerLeft';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['player_id' => $this->playerId, 'name' => $this->name];
    }
}
