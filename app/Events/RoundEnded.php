<?php

namespace App\Events;

use App\Models\Player;
use App\Models\Round;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoundEnded implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    /** @param Collection<int, Player> $players */
    public function __construct(public Round $round, public Collection $players) {}

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
        return 'RoundEnded';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'round' => $this->round->number,
            'word' => $this->round->word,
            'scores' => $this->players->map(fn (Player $player): array => [
                'id' => $player->id,
                'name' => $player->name,
                'score' => $player->score,
            ])->values()->all(),
        ];
    }
}
