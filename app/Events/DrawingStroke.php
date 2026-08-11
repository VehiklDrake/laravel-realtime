<?php

namespace App\Events;

use App\Models\DrawingStroke as DrawingStrokeModel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DrawingStroke implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public DrawingStrokeModel $stroke) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("game.{$this->stroke->round->game_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'DrawingStroke';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'stroke' => [
                'id' => $this->stroke->id,
                'player_id' => $this->stroke->player_id,
                'client_id' => $this->stroke->client_id,
                'from_x' => $this->stroke->from_x,
                'from_y' => $this->stroke->from_y,
                'to_x' => $this->stroke->to_x,
                'to_y' => $this->stroke->to_y,
                'color' => $this->stroke->color,
                'size' => $this->stroke->size,
            ],
        ];
    }
}
