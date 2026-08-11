<?php

use App\Actions\Games\FinishRound;
use App\Actions\Games\StartNextRound;
use App\Enums\GameStatus;
use App\Events\GameFinished;
use App\Events\RoundEnded;
use App\Events\RoundStarted;
use App\Jobs\AdvanceRound;
use App\Jobs\EndRound;
use App\Models\Game;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Date::setTestNow('2026-08-05 12:00:00');
    $this->game = Game::factory()->create([
        'status' => GameStatus::Playing,
        'current_round' => 1,
        'total_rounds' => 2,
    ]);
    $this->first = Player::factory()->host()->for($this->game)->create();
    $this->second = Player::factory()->for($this->game)->create();
    $this->round = Round::factory()->for($this->game)->for($this->first, 'drawer')->active()->create(['number' => 1]);
});

afterEach(fn () => Date::setTestNow());

it('ends an expired round and starts the next one with the next drawer', function () {
    Queue::fake();
    Event::fake([RoundEnded::class, RoundStarted::class]);

    (new EndRound($this->round->id))->handle(app(FinishRound::class));

    expect($this->round->refresh()->ended_at)->not->toBeNull()
        ->and($this->game->refresh()->current_round)->toBe(1)
        ->and($this->game->rounds()->count())->toBe(1);
    Event::assertDispatched(RoundEnded::class);
    Queue::assertPushed(AdvanceRound::class, fn (AdvanceRound $job) => $job->roundId === $this->round->id);

    Date::setTestNow($this->round->ended_at->addSeconds(5));
    (new AdvanceRound($this->round->id))->handle(app(StartNextRound::class));

    $nextRound = $this->game->rounds()->where('number', 2)->sole();
    expect($this->game->refresh()->current_round)->toBe(2)
        ->and($nextRound->drawer->is($this->second))->toBeTrue();

    Event::assertDispatched(RoundStarted::class);
    Queue::assertPushed(EndRound::class, fn (EndRound $job) => $job->roundId === $nextRound->id);
});

it('finishes the game after its final round', function () {
    $this->round->update(['number' => 2]);
    $this->game->update(['current_round' => 2]);
    Event::fake([RoundEnded::class, GameFinished::class]);

    (new EndRound($this->round->id))->handle(app(FinishRound::class));

    expect($this->game->refresh()->status)->toBe(GameStatus::Playing);

    Date::setTestNow($this->round->refresh()->ended_at->addSeconds(5));
    (new AdvanceRound($this->round->id))->handle(app(StartNextRound::class));

    expect($this->game->refresh()->status)->toBe(GameStatus::Finished)
        ->and($this->round->refresh()->ended_at)->not->toBeNull();
    Event::assertDispatched(RoundEnded::class);
    Event::assertDispatched(GameFinished::class);
});

it('ignores a duplicate or stale round-ending job', function () {
    $this->round->update(['ended_at' => now()]);
    Event::fake([RoundEnded::class, RoundStarted::class, GameFinished::class]);

    (new EndRound($this->round->id))->handle(app(FinishRound::class));

    expect($this->game->rounds()->count())->toBe(1);
    Event::assertNotDispatched(RoundEnded::class);
    Event::assertNotDispatched(RoundStarted::class);
    Event::assertNotDispatched(GameFinished::class);
});

it('lets a connected browser progress an expired timer when the queue worker is unavailable', function () {
    Queue::fake();
    Event::fake([RoundEnded::class, RoundStarted::class]);
    Date::setTestNow($this->round->ends_at);

    $this->withSession(sessionFor($this->first))
        ->postJson(route('games.progress', $this->game))
        ->assertSuccessful()
        ->assertJsonPath('round.result.reason', 'timeout')
        ->assertJsonPath(
            'round.result.intermission_ends_at',
            $this->round->ends_at->addSeconds(5)->toISOString(),
        );

    expect($this->round->refresh()->ended_at)->toEqual($this->round->ends_at)
        ->and($this->game->refresh()->current_round)->toBe(1);

    Date::setTestNow($this->round->ended_at->addSeconds(5));
    $this->withSession(sessionFor($this->first))
        ->postJson(route('games.progress', $this->game))
        ->assertSuccessful()
        ->assertJsonPath('game.current_round', 2)
        ->assertJsonCount(0, 'round.messages');

    expect($this->game->refresh()->current_round)->toBe(2)
        ->and($this->game->rounds()->where('number', 2)->exists())->toBeTrue();
});
