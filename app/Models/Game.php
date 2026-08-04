<?php

namespace App\Models;

use App\Enums\GameStatus;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property GameStatus $status
 * @property int $current_round
 * @property int $total_rounds
 * @property int $round_duration
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'status', 'current_round', 'total_rounds', 'round_duration'])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'waiting',
        'current_round' => 0,
        'total_rounds' => 3,
        'round_duration' => 60,
    ];

    /** @return HasMany<Player, $this> */
    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    /** @return HasMany<Round, $this> */
    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }

    public function activeRound(): ?Round
    {
        if ($this->current_round === 0) {
            return null;
        }

        return $this->rounds()->where('number', $this->current_round)->first();
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => GameStatus::class,
            'current_round' => 'integer',
            'total_rounds' => 'integer',
            'round_duration' => 'integer',
        ];
    }
}
