<?php

use App\Enums\GameStatus;
use App\Events\PlayerJoined;
use App\Models\Game;
use Illuminate\Support\Facades\Event;

it('creates a waiting room and makes its creator the host', function () {
    Event::fake([PlayerJoined::class]);

    $response = $this->post(route('games.store'), ['name' => 'Taylor']);

    $game = Game::query()->with('players')->sole();
    $host = $game->players->sole();

    $response->assertRedirect(route('games.show', $game));

    expect($game->code)->toBeRoomCode()
        ->and($game->status)->toBe(GameStatus::Waiting)
        ->and($game->current_round)->toBe(0)
        ->and($host->name)->toBe('Taylor')
        ->and($host->is_host)->toBeTrue()
        ->and(session("games.{$game->id}.player_token"))->toBe($host->token);

    Event::assertDispatched(PlayerJoined::class, fn (PlayerJoined $event) => $event->player->is($host));
});

it('uses the configured game defaults', function () {
    $this->post(route('games.store'), ['name' => 'Taylor']);

    expect(Game::query()->sole())
        ->total_rounds->toBe(config('game.rounds'))
        ->round_duration->toBe(config('game.round_duration'));
});

it('validates host names', function (mixed $name) {
    $this->post(route('games.store'), ['name' => $name])
        ->assertSessionHasErrors('name');

    expect(Game::query()->exists())->toBeFalse();
})->with([
    'missing' => null,
    'empty' => '',
    'too short' => 'A',
    'too long' => str_repeat('a', 25),
    'contains markup' => '<b>Artist</b>',
]);
