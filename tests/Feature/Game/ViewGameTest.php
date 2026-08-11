<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Guess;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->game = Game::factory()->create();
    $this->host = Player::factory()->host()->for($this->game)->create();
});

afterEach(fn () => Date::setTestNow());

it('asks visitors to join when they do not have a room session', function () {
    $this->get(route('games.show', $this->game))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Games/Join')
            ->where('code', $this->game->code));
});

it('shows room state to a joined player', function () {
    $this->withSession(sessionFor($this->host))
        ->get(route('games.show', $this->game))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Games/Room')
            ->where('game.code', $this->game->code)
            ->where('player.id', $this->host->id)
            ->where('player.is_host', true)
            ->has('game.players', 1));
});

it('reveals the prompt only to the active drawer', function () {
    $guesser = Player::factory()->for($this->game)->create();
    $round = Round::factory()->for($this->game)->for($this->host, 'drawer')->active()->create([
        'number' => 1,
        'word' => 'butterfly',
    ]);
    $this->game->update(['status' => GameStatus::Playing, 'current_round' => 1]);

    $this->withSession(sessionFor($this->host))
        ->getJson(route('games.state', $this->game))
        ->assertSuccessful()
        ->assertJsonPath('round.id', $round->id)
        ->assertJsonPath('round.word', 'butterfly')
        ->assertJsonPath('round.masked_word', null);

    $this->withSession(sessionFor($guesser))
        ->getJson(route('games.state', $this->game))
        ->assertSuccessful()
        ->assertJsonPath('round.word', null)
        ->assertJsonPath('round.masked_word', '_________')
        ->assertJsonMissing(['word' => 'butterfly']);
});

it('rejects state requests from non-members', function () {
    $this->getJson(route('games.state', $this->game))->assertForbidden();
});

it('starts each round with an empty guess box', function () {
    $guesser = Player::factory()->for($this->game)->create();
    $firstRound = Round::factory()->for($this->game)->for($this->host, 'drawer')->ended()->create([
        'number' => 1,
        'word' => 'pizza',
    ]);
    Guess::factory()->for($firstRound)->for($guesser)->create([
        'message' => 'pizzas',
        'is_correct' => false,
    ]);
    $secondRound = Round::factory()->for($this->game)->for($guesser, 'drawer')->active()->create([
        'number' => 2,
        'word' => 'octopus',
    ]);
    Guess::factory()->for($secondRound)->for($this->host)->create([
        'message' => 'test',
        'is_correct' => false,
    ]);
    $this->game->update(['status' => GameStatus::Playing, 'current_round' => 2]);

    $this->withSession(sessionFor($this->host))
        ->getJson(route('games.state', $this->game))
        ->assertSuccessful()
        ->assertJsonCount(1, 'round.messages')
        ->assertJsonPath('round.messages.0.message', 'test')
        ->assertJsonPath('round.messages.0.round_number', 2)
        ->assertJsonMissing(['message' => 'pizzas']);
});

it('describes who solved an ended round and how many attempts it took', function () {
    Date::setTestNow('2026-08-05 12:00:20');
    $guesser = Player::factory()->for($this->game)->create();
    $round = Round::factory()->for($this->game)->for($this->host, 'drawer')->create([
        'number' => 1,
        'word' => 'pizza',
        'started_at' => now()->subSeconds(20),
        'ends_at' => now()->addSeconds(40),
        'ended_at' => now(),
    ]);
    Guess::factory()->for($round)->for($guesser)->create([
        'message' => 'pie',
        'is_correct' => false,
        'created_at' => $round->started_at->addSeconds(5),
    ]);
    Guess::factory()->for($round)->for($guesser)->create([
        'message' => 'pizza',
        'is_correct' => true,
        'created_at' => $round->started_at->addSeconds(12),
    ]);
    $this->game->update(['status' => GameStatus::Playing, 'current_round' => 1]);

    $this->withSession(sessionFor($this->host))
        ->getJson(route('games.state', $this->game))
        ->assertSuccessful()
        ->assertJsonPath('round.word', 'pizza')
        ->assertJsonPath('round.result.reason', 'correct')
        ->assertJsonPath('round.result.correct_guesses.0.player', $guesser->name)
        ->assertJsonPath('round.result.correct_guesses.0.seconds', 12)
        ->assertJsonPath('round.result.correct_guesses.0.guess_count', 2);
});
