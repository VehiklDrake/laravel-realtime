<?php

namespace App\Actions\Games;

use App\Enums\GameStatus;
use App\Events\ChatMessage;
use App\Events\CorrectGuess;
use App\Models\Game;
use App\Models\Guess;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmitGuess
{
    public function __construct(private FinishRound $finishRound) {}

    /** @return array{correct: bool, points: int, message: array<string, mixed>} */
    public function handle(Game $game, Player $player, int $roundId, string $message): array
    {
        $result = DB::transaction(function () use ($game, $player, $roundId, $message): array {
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);
            abort_unless($lockedGame->status === GameStatus::Playing, 409, 'The game is not active.');

            $lockedRound = Round::query()
                ->whereBelongsTo($lockedGame)
                ->lockForUpdate()
                ->find($roundId);
            abort_unless(
                $lockedRound instanceof Round && $lockedRound->number === $lockedGame->current_round,
                409,
                'This guess belongs to a previous round.',
            );

            $lockedPlayer = Player::query()->lockForUpdate()->findOrFail($player->id);
            abort_if($lockedRound->drawer_id === $lockedPlayer->id, 403, 'The drawer cannot submit guesses.');
            abort_if(
                $lockedRound->ended_at !== null || now()->greaterThanOrEqualTo($lockedRound->ends_at),
                409,
                'This round has ended.',
            );

            if ($lockedRound->guesses()->whereBelongsTo($lockedPlayer)->where('is_correct', true)->exists()) {
                throw ValidationException::withMessages(['message' => 'You already guessed this word.']);
            }

            $isCorrect = $this->normalize($message) === $this->normalize($lockedRound->word);
            $points = $isCorrect
                ? (int) config('game.correct_guess_points') + max(0, (int) now()->diffInSeconds($lockedRound->ends_at, false))
                : 0;

            $guess = $lockedRound->guesses()->create([
                'player_id' => $lockedPlayer->id,
                'message' => $message,
                'is_correct' => $isCorrect,
                'points' => $points,
            ]);

            if ($isCorrect) {
                $lockedPlayer->increment('score', $points);
                $lockedRound->drawer()->increment('score', (int) config('game.drawer_guess_bonus'));
            }

            return [
                'guess' => $guess->load(['player', 'round']),
                'round' => $lockedRound,
                'correct' => $isCorrect,
                'points' => $points,
            ];
        });

        /** @var Guess $guess */
        $guess = $result['guess'];
        /** @var Round $round */
        $round = $result['round'];

        if ($result['correct']) {
            $guess->setRelation('player', $player->refresh());
            CorrectGuess::dispatch($guess, $result['points']);

            $eligibleGuesserCount = $game->players()->whereKeyNot($round->drawer_id)->count();
            $correctGuesserCount = $round->guesses()->where('is_correct', true)->distinct()->count('player_id');

            if ($eligibleGuesserCount > 0 && $correctGuesserCount >= $eligibleGuesserCount) {
                $this->finishRound->handle($round);
            }
        } else {
            ChatMessage::dispatch($guess->load('player'));
        }

        return [
            'correct' => $result['correct'],
            'points' => $result['points'],
            'message' => [
                'id' => $guess->id,
                'round_id' => $round->id,
                'round_number' => $round->number,
                'player_id' => $guess->player_id,
                'player' => $guess->player->name,
                'message' => $guess->is_correct ? 'guessed correctly!' : $guess->message,
                'is_correct' => $guess->is_correct,
            ],
        ];
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::squish($value));
    }
}
