<?php

use App\Enums\GameStatus;
use App\Events\CanvasCleared;
use App\Events\DrawingStrokes;
use App\Models\DrawingStroke as DrawingStrokeModel;
use App\Models\Game;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->game = Game::factory()->create(['status' => GameStatus::Playing, 'current_round' => 1]);
    $this->drawer = Player::factory()->host()->for($this->game)->create();
    $this->guesser = Player::factory()->for($this->game)->create();
    $this->round = Round::factory()->for($this->game)->for($this->drawer, 'drawer')->active()->create(['number' => 1]);
    $this->stroke = [
        'from_x' => 0.1,
        'from_y' => 0.2,
        'to_x' => 0.25,
        'to_y' => 0.35,
        'color' => '#18181b',
        'size' => 6,
    ];
});

it('records and broadcasts drawer strokes', function () {
    Event::fake([DrawingStrokes::class]);

    $this->withSession(sessionFor($this->drawer))
        ->postJson(route('games.drawing.store', $this->game), [
            'round_id' => $this->round->id,
            'strokes' => [$this->stroke],
        ])
        ->assertSuccessful()
        ->assertJsonPath('recorded', 1);

    $stroke = DrawingStrokeModel::query()->sole();
    expect($stroke->round->is($this->round))->toBeTrue()
        ->and($stroke->player->is($this->drawer))->toBeTrue()
        ->and($stroke->color)->toBe('#18181b');
    Event::assertDispatched(DrawingStrokes::class, fn (DrawingStrokes $event) => $event->strokes->contains($stroke)
        && $event->broadcastWith()['round_id'] === $this->round->id);
});

it('persists an entire stroke batch before queueing one broadcast', function () {
    Queue::fake();
    $secondStroke = [...$this->stroke, 'from_x' => 0.25, 'to_x' => 0.4];

    $this->withSession(sessionFor($this->drawer))
        ->postJson(route('games.drawing.store', $this->game), [
            'round_id' => $this->round->id,
            'strokes' => [$this->stroke, $secondStroke],
        ])
        ->assertSuccessful()
        ->assertJsonPath('recorded', 2);

    expect(DrawingStrokeModel::query()->count())->toBe(2);
    Queue::assertPushed(BroadcastEvent::class, 1);
});

it('allows only the current drawer to draw', function () {
    $this->withSession(sessionFor($this->guesser))
        ->postJson(route('games.drawing.store', $this->game), [
            'round_id' => $this->round->id,
            'strokes' => [$this->stroke],
        ])
        ->assertForbidden();
});

it('validates stroke boundaries', function (array $override, string $field) {
    $stroke = [...$this->stroke, ...$override];

    $this->withSession(sessionFor($this->drawer))
        ->postJson(route('games.drawing.store', $this->game), [
            'round_id' => $this->round->id,
            'strokes' => [$stroke],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors("strokes.0.{$field}");
})->with([
    'negative coordinate' => [['from_x' => -0.1], 'from_x'],
    'coordinate above one' => [['to_y' => 1.1], 'to_y'],
    'invalid color' => [['color' => 'javascript:red'], 'color'],
    'zero brush size' => [['size' => 0], 'size'],
    'oversized brush' => [['size' => 33], 'size'],
]);

it('clears only the active canvas and broadcasts the reset', function () {
    $oldRound = Round::factory()->for($this->game)->for($this->guesser, 'drawer')->ended()->create(['number' => 0]);
    DrawingStrokeModel::factory()->for($oldRound)->for($this->guesser)->create();
    DrawingStrokeModel::factory()->count(2)->for($this->round)->for($this->drawer)->create();
    Event::fake([CanvasCleared::class]);

    $this->withSession(sessionFor($this->drawer))
        ->deleteJson(route('games.drawing.destroy', $this->game), ['round_id' => $this->round->id])
        ->assertNoContent();

    expect($this->round->drawingStrokes()->count())->toBe(0)
        ->and($oldRound->drawingStrokes()->count())->toBe(1);
    Event::assertDispatched(CanvasCleared::class);
});

it('rejects delayed drawing batches from a previous round', function () {
    $nextRound = Round::factory()->for($this->game)->for($this->guesser, 'drawer')->active()->create(['number' => 2]);
    $this->round->update(['ended_at' => now()]);
    $this->game->update(['current_round' => 2]);

    $this->withSession(sessionFor($this->drawer))
        ->postJson(route('games.drawing.store', $this->game), [
            'round_id' => $this->round->id,
            'strokes' => [$this->stroke],
        ])
        ->assertConflict();

    expect($this->round->drawingStrokes()->count())->toBe(0)
        ->and($nextRound->drawingStrokes()->count())->toBe(0);
});

it('rejects delayed canvas clears from a previous round', function () {
    $nextRound = Round::factory()->for($this->game)->for($this->guesser, 'drawer')->active()->create(['number' => 2]);
    DrawingStrokeModel::factory()->for($nextRound)->for($this->guesser)->create();
    $this->round->update(['ended_at' => now()]);
    $this->game->update(['current_round' => 2]);

    $this->withSession(sessionFor($this->drawer))
        ->deleteJson(route('games.drawing.destroy', $this->game), ['round_id' => $this->round->id])
        ->assertConflict();

    expect($nextRound->drawingStrokes()->count())->toBe(1);
});
