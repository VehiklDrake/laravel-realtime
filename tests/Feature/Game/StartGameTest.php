<?php

use App\Enums\GameStatus;
use App\Events\GameStarted;
use App\Events\RoundStarted;
use App\Jobs\EndRound;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->game = Game::factory()->create();
    $this->host = Player::factory()->host()->for($this->game)->create();
    $this->guest = Player::factory()->for($this->game)->create();
});

it('lets the host start a game and schedules the first round end', function () {
    Queue::fake();
    Event::fake([GameStarted::class, RoundStarted::class]);

    $this->withSession(sessionFor($this->host))
        ->postJson(route('games.start', $this->game))
        ->assertSuccessful()
        ->assertJsonPath('game.status', GameStatus::Playing->value)
        ->assertJsonPath('game.current_round', 1);

    $this->game->refresh();
    $round = $this->game->rounds()->with('drawer')->sole();

    expect($this->game->status)->toBe(GameStatus::Playing)
        ->and($round->number)->toBe(1)
        ->and($round->drawer->is($this->host))->toBeTrue()
        ->and($round->word)->toBeIn(config('game.words'))
        ->and((int) $round->started_at->diffInSeconds($round->ends_at))->toBe($this->game->round_duration);

    Event::assertDispatched(GameStarted::class);
    Event::assertDispatched(RoundStarted::class);
    Queue::assertPushed(EndRound::class, fn (EndRound $job) => $job->roundId === $round->id);
});

it('requires the host to start the game', function () {
    $this->withSession(sessionFor($this->guest))
        ->postJson(route('games.start', $this->game))
        ->assertForbidden();
});

it('requires at least two players', function () {
    $this->guest->delete();

    $this->withSession(sessionFor($this->host))
        ->postJson(route('games.start', $this->game))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('players');
});

it('cannot start an active game twice', function () {
    $this->game->update(['status' => GameStatus::Playing]);

    $this->withSession(sessionFor($this->host))
        ->postJson(route('games.start', $this->game))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('game');
});

it('returns the started game when the realtime transport is unavailable', function () {
    Exceptions::fake(BroadcastException::class);

    config([
        'broadcasting.default' => 'failing',
        'broadcasting.connections.failing' => ['driver' => 'failing'],
    ]);

    Broadcast::extend('failing', fn (): Broadcaster => new class implements Broadcaster
    {
        public function auth(mixed $request): never
        {
            throw new BroadcastException('Realtime transport unavailable.');
        }

        public function validAuthenticationResponse(mixed $request, mixed $result): never
        {
            throw new BroadcastException('Realtime transport unavailable.');
        }

        public function broadcast(array $channels, mixed $event, array $payload = []): never
        {
            throw new BroadcastException('Realtime transport unavailable.');
        }
    });

    Queue::fake();

    $this->withSession(sessionFor($this->host))
        ->postJson(route('games.start', $this->game))
        ->assertSuccessful()
        ->assertJsonPath('game.status', GameStatus::Playing->value)
        ->assertJsonPath('game.current_round', 1);

    Exceptions::assertReportedCount(2);
});
