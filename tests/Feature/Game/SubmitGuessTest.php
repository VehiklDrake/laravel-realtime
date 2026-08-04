<?php

use App\Enums\GameStatus;
use App\Events\ChatMessage;
use App\Events\CorrectGuess;
use App\Models\Game;
use App\Models\Guess;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Date::setTestNow('2026-08-04 12:00:00');

    $this->game = Game::factory()->create(['status' => GameStatus::Playing, 'current_round' => 1]);
    $this->drawer = Player::factory()->host()->for($this->game)->create();
    $this->guesser = Player::factory()->for($this->game)->create();
    $this->otherGuesser = Player::factory()->for($this->game)->create();
    $this->round = Round::factory()->for($this->game)->for($this->drawer, 'drawer')->active()->create([
        'number' => 1,
        'word' => 'ice cream',
        'started_at' => now(),
        'ends_at' => now()->addSeconds(60),
    ]);
});

afterEach(fn () => Date::setTestNow());

it('broadcasts and stores an incorrect guess as chat', function () {
    Event::fake([ChatMessage::class]);

    $this->withSession(sessionFor($this->guesser))
        ->postJson(route('games.guesses.store', $this->game), [
            'round_id' => $this->round->id,
            'message' => 'A snowman',
        ])
        ->assertSuccessful()
        ->assertJsonPath('correct', false)
        ->assertJsonPath('message.message', 'A snowman')
        ->assertJsonPath('message.round_number', 1);

    $guess = Guess::query()->sole();
    expect($guess->message)->toBe('A snowman')->and($guess->is_correct)->toBeFalse();
    Event::assertDispatched(ChatMessage::class, fn (ChatMessage $event) => $event->guess->is($guess));
});

it('stores a guess and queues its broadcast without blocking the response', function () {
    Queue::fake();

    $this->withSession(sessionFor($this->guesser))
        ->postJson(route('games.guesses.store', $this->game), [
            'round_id' => $this->round->id,
            'message' => 'A castle',
        ])
        ->assertSuccessful()
        ->assertJsonPath('correct', false);

    expect(Guess::query()->sole()->message)->toBe('A castle');
    Queue::assertPushed(BroadcastEvent::class, 1);
});

it('normalizes a correct answer and awards a time bonus exactly once', function () {
    Event::fake([CorrectGuess::class]);

    $this->withSession(sessionFor($this->guesser))
        ->postJson(route('games.guesses.store', $this->game), [
            'round_id' => $this->round->id,
            'message' => '  ICE   CREAM ',
        ])
        ->assertSuccessful()
        ->assertJsonPath('correct', true)
        ->assertJsonPath('points', 160);

    expect($this->guesser->refresh()->score)->toBe(160)
        ->and($this->drawer->refresh()->score)->toBe(config('game.drawer_guess_bonus'))
        ->and(Guess::query()->sole()->is_correct)->toBeTrue();

    Event::assertDispatched(CorrectGuess::class, fn (CorrectGuess $event) => $event->guess->player->is($this->guesser) && $event->points === 160);

    $this->withSession(sessionFor($this->guesser))
        ->postJson(route('games.guesses.store', $this->game), [
            'round_id' => $this->round->id,
            'message' => 'ice cream',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');

    expect($this->guesser->refresh()->score)->toBe(160);
});

it('does not expose the correct answer in the event payload', function () {
    Event::fake([CorrectGuess::class]);

    $this->withSession(sessionFor($this->guesser))
        ->postJson(route('games.guesses.store', $this->game), [
            'round_id' => $this->round->id,
            'message' => 'ice cream',
        ])
        ->assertSuccessful();

    Event::assertDispatched(CorrectGuess::class, fn (CorrectGuess $event) => ! str_contains(json_encode($event->broadcastWith(), JSON_THROW_ON_ERROR), 'ice cream'));
});

it('prevents the drawer from guessing', function () {
    $this->withSession(sessionFor($this->drawer))
        ->postJson(route('games.guesses.store', $this->game), [
            'round_id' => $this->round->id,
            'message' => 'ice cream',
        ])
        ->assertForbidden();
});

it('rejects guesses after the deadline', function () {
    Date::setTestNow($this->round->ends_at->addSecond());

    $this->withSession(sessionFor($this->guesser))
        ->postJson(route('games.guesses.store', $this->game), [
            'round_id' => $this->round->id,
            'message' => 'ice cream',
        ])
        ->assertConflict();
});

it('validates guess messages', function (mixed $message) {
    $this->withSession(sessionFor($this->guesser))
        ->postJson(route('games.guesses.store', $this->game), [
            'round_id' => $this->round->id,
            'message' => $message,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');
})->with([
    'missing' => null,
    'empty' => '',
    'too long' => str_repeat('x', 121),
]);

it('rejects a delayed guess after the next round has started', function () {
    $nextRound = Round::factory()->for($this->game)->for($this->guesser, 'drawer')->active()->create([
        'number' => 2,
        'word' => 'snowman',
    ]);
    $this->round->update(['ended_at' => now()]);
    $this->game->update(['current_round' => 2]);

    $this->withSession(sessionFor($this->guesser))
        ->postJson(route('games.guesses.store', $this->game), [
            'round_id' => $this->round->id,
            'message' => 'snowman',
        ])
        ->assertConflict();

    expect(Guess::query()->count())->toBe(0)
        ->and($this->guesser->refresh()->score)->toBe(0)
        ->and($nextRound->guesses()->count())->toBe(0);
});

it('stores repeated incorrect guesses without scoring or advancing the round', function () {
    foreach (range(1, 3) as $attempt) {
        $this->withSession(sessionFor($this->guesser))
            ->postJson(route('games.guesses.store', $this->game), [
                'round_id' => $this->round->id,
                'message' => 'test',
            ])
            ->assertSuccessful()
            ->assertJsonPath('correct', false)
            ->assertJsonPath('points', 0);
    }

    expect(Guess::query()->count())->toBe(3)
        ->and(Guess::query()->where('is_correct', true)->count())->toBe(0)
        ->and($this->guesser->refresh()->score)->toBe(0)
        ->and($this->game->refresh()->current_round)->toBe(1);
});
