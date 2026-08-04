<?php

namespace App\Models;

use Database\Factories\GuessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $round_id
 * @property int $player_id
 * @property string $message
 * @property bool $is_correct
 * @property int $points
 */
#[Fillable(['round_id', 'player_id', 'message', 'is_correct', 'points'])]
class Guess extends Model
{
    /** @use HasFactory<GuessFactory> */
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
            'is_correct' => 'boolean',
            'points' => 'integer',
        ];
    }
}
