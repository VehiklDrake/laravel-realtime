<?php

namespace App\Models;

use Database\Factories\DrawingStrokeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $round_id
 * @property int $player_id
 * @property string|null $client_id
 * @property float $from_x
 * @property float $from_y
 * @property float $to_x
 * @property float $to_y
 * @property string $color
 * @property int $size
 */
#[Fillable(['round_id', 'player_id', 'client_id', 'from_x', 'from_y', 'to_x', 'to_y', 'color', 'size'])]
class DrawingStroke extends Model
{
    /** @use HasFactory<DrawingStrokeFactory> */
    use HasFactory;

    /** @return BelongsTo<Round, $this> */
    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_x' => 'float',
            'from_y' => 'float',
            'to_x' => 'float',
            'to_y' => 'float',
            'size' => 'integer',
        ];
    }
}
