<?php

namespace App\Models;

use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $game_id
 * @property string $name
 * @property string $token
 * @property int $score
 * @property bool $is_host
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['game_id', 'name', 'token', 'score', 'is_host'])]
#[Hidden(['token'])]
class Player extends Authenticatable
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory;

    protected $attributes = [
        'score' => 0,
        'is_host' => false,
    ];

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<Round, $this> */
    public function drawnRounds(): HasMany
    {
        return $this->hasMany(Round::class, 'drawer_id');
    }

    /** @return HasMany<Guess, $this> */
    public function guesses(): HasMany
    {
        return $this->hasMany(Guess::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'is_host' => 'boolean',
        ];
    }
}
