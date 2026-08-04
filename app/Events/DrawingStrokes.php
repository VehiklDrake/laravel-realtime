<?php

namespace App\Events;

use App\Models\DrawingStroke;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DrawingStrokes implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param Collection<int, DrawingStroke> $strokes */
    public function __construct(public Collection $strokes)
    {
        if ($strokes->isEmpty()) {
            throw new \InvalidArgumentException('A drawing broadcast requires at least one stroke.');
        }
    }

    /** @return array<int, PresenceChannel> */
    public function broadcastOn(): array
    {
        /** @var DrawingStroke $stroke */
        $stroke = $this->strokes->first();

        return [
            new PresenceChannel("game.{$stroke->round->game_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'DrawingStrokes';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'round_id' => $this->strokes->first()->round_id,
            'strokes' => $this->strokes->map(fn (DrawingStroke $stroke): array => [
                'id' => $stroke->id,
                'player_id' => $stroke->player_id,
                'from_x' => $stroke->from_x,
                'from_y' => $stroke->from_y,
                'to_x' => $stroke->to_x,
                'to_y' => $stroke->to_y,
                'color' => $stroke->color,
                'size' => $stroke->size,
            ])->values()->all(),
        ];
    }
}
