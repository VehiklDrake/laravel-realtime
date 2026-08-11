<?php

namespace App\Events;

use App\Models\Guess;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CorrectGuess implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Guess $guess, public int $points) {}

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
        return 'CorrectGuess';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'player' => [
                'id' => $this->guess->player->id,
                'name' => $this->guess->player->name,
                'score' => $this->guess->player->score,
            ],
            'points' => $this->points,
            'message' => [
                'id' => $this->guess->id,
                'round_id' => $this->guess->round_id,
                'round_number' => $this->guess->round->number,
                'player_id' => $this->guess->player_id,
                'player' => $this->guess->player->name,
                'message' => 'guessed correctly!',
                'is_correct' => true,
            ],
        ];
    }
}
