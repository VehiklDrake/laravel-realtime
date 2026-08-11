<?php

namespace App\Events;

use App\Models\Round;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CanvasCleared implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Round $round) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("game.{$this->round->game_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CanvasCleared';
    }

    /** @return array<string, int> */
    public function broadcastWith(): array
    {
        return ['round_id' => $this->round->id];
    }
}
