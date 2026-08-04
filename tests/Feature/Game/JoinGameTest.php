<?php

use App\Enums\GameStatus;
use App\Events\PlayerJoined;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->game = Game::factory()->create();
    $this->host = Player::factory()->host()->for($this->game)->create();
});

it('joins a waiting room using a case-insensitive code', function () {
    Event::fake([PlayerJoined::class]);

    $response = $this->post(route('games.join'), [
        'code' => strtolower($this->game->code),
        'name' => 'Nuno',
    ]);

    $player = $this->game->players()->where('name', 'Nuno')->sole();

    $response->assertRedirect(route('games.show', $this->game));
    expect(session("games.{$this->game->id}.player_token"))->toBe($player->token);
    Event::assertDispatched(PlayerJoined::class, fn (PlayerJoined $event) => $event->player->is($player));
});

it('reuses the session player instead of creating a duplicate', function () {
    $player = Player::factory()->for($this->game)->create(['name' => 'Nuno']);

    $this->withSession(sessionFor($player))->post(route('games.join'), [
        'code' => $this->game->code,
        'name' => 'Nuno',
    ])->assertRedirect(route('games.show', $this->game));

    expect($this->game->players()->count())->toBe(2);
});

it('rejects unknown rooms', function () {
    $this->post(route('games.join'), ['code' => 'ZZZZZZ', 'name' => 'Nuno'])
        ->assertSessionHasErrors('code');
});

it('requires names to be unique within a room regardless of case', function () {
    Player::factory()->for($this->game)->create(['name' => 'Nuno']);

    $this->post(route('games.join'), ['code' => $this->game->code, 'name' => 'nUNO'])
        ->assertSessionHasErrors('name');
});

it('does not allow joining after play begins', function () {
    $this->game->update(['status' => GameStatus::Playing]);

    $this->post(route('games.join'), ['code' => $this->game->code, 'name' => 'Nuno'])
        ->assertSessionHasErrors('code');
});

it('caps rooms at the configured player limit', function () {
    Player::factory()->count(config('game.max_players') - 1)->for($this->game)->create();

    $this->post(route('games.join'), ['code' => $this->game->code, 'name' => 'Latecomer'])
        ->assertSessionHasErrors('code');
});
