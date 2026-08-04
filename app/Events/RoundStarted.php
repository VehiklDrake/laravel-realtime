<?php

namespace App\Events;

use App\Models\Round;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoundStarted implements ShouldBroadcast, ShouldDispatchAfterCommit
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
        return 'RoundStarted';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'round' => [
                'id' => $this->round->id,
                'number' => $this->round->number,
                'drawer_id' => $this->round->drawer_id,
                'drawer_name' => $this->round->drawer->name,
                'started_at' => $this->round->started_at->toISOString(),
                'ends_at' => $this->round->ends_at->toISOString(),
            ],
        ];
    }
}
