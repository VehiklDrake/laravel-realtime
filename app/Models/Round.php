<?php

namespace App\Models;

use Database\Factories\RoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $game_id
 * @property int $drawer_id
 * @property int $number
 * @property string $word
 * @property Carbon $started_at
 * @property Carbon $ends_at
 * @property Carbon|null $ended_at
 */
#[Fillable(['game_id', 'drawer_id', 'number', 'word', 'started_at', 'ends_at', 'ended_at'])]
class Round extends Model
{
    /** @use HasFactory<RoundFactory> */
    use HasFactory;

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function drawer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'drawer_id');
    }

    /** @return HasMany<Guess, $this> */
    public function guesses(): HasMany
    {
        return $this->hasMany(Guess::class);
    }

    /** @return HasMany<DrawingStroke, $this> */
    public function drawingStrokes(): HasMany
    {
        return $this->hasMany(DrawingStroke::class);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null && $this->ends_at->isFuture();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
