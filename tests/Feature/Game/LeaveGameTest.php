<?php

use App\Enums\GameStatus;
use App\Events\PlayerLeft;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->game = Game::factory()->create();
    $this->host = Player::factory()->host()->for($this->game)->create();
    $this->guest = Player::factory()->for($this->game)->create();
});

it('lets a player leave a waiting room', function () {
    Event::fake([PlayerLeft::class]);

    $this->withSession(sessionFor($this->guest))
        ->delete(route('games.players.destroy', $this->game))
        ->assertRedirect(route('home'));

    $this->assertModelMissing($this->guest);
    expect(session("games.{$this->game->id}.player_token"))->toBeNull();
    Event::assertDispatched(PlayerLeft::class, fn (PlayerLeft $event) => $event->playerId === $this->guest->id);
});

it('transfers hosting when the host leaves', function () {
    $this->withSession(sessionFor($this->host))
        ->delete(route('games.players.destroy', $this->game))
        ->assertRedirect(route('home'));

    expect($this->guest->refresh()->is_host)->toBeTrue();
});

it('keeps active players in the game for reconnecting', function () {
    $this->game->update(['status' => GameStatus::Playing]);

    $this->withSession(sessionFor($this->guest))
        ->deleteJson(route('games.players.destroy', $this->game))
        ->assertConflict();

    $this->assertModelExists($this->guest);
});

it('navigates home without submitting the leave endpoint after a game finishes', function () {
    $room = file_get_contents(resource_path('js/pages/Games/Room.vue'));

    expect($room)
        ->toContain("import { Form, Head, Link, useHttp } from '@inertiajs/vue3';")
        ->toContain("import { home } from '@/routes';")
        ->toContain('v-if="currentGame.status === \'waiting\'"')
        ->toContain(':href="home()"');
});
