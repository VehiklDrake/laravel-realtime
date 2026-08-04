<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Guess;
use App\Models\Player;
use App\Models\Round;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->game = Game::factory()->create();
    $this->host = Player::factory()->host()->for($this->game)->create();
});

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

it('keeps guess history visible when a new round starts', function () {
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
        ->assertJsonCount(2, 'round.messages')
        ->assertJsonPath('round.messages.0.message', 'pizzas')
        ->assertJsonPath('round.messages.0.round_number', 1)
        ->assertJsonPath('round.messages.1.message', 'test')
        ->assertJsonPath('round.messages.1.round_number', 2);
});
