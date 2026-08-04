<?php

namespace App\Events;

use App\Models\Guess;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessage implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Guess $guess) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("game.{$this->guess->round->game_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ChatMessage';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->guess->id,
                'round_id' => $this->guess->round_id,
                'round_number' => $this->guess->round->number,
                'player_id' => $this->guess->player_id,
                'player' => $this->guess->player->name,
                'message' => $this->guess->message,
                'is_correct' => false,
            ],
        ];
    }
}
