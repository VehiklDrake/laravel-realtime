<?php

use App\Enums\GameStatus;

it('defines every game lifecycle state', function () {
    expect(GameStatus::cases())->toHaveCount(3)
        ->and(GameStatus::Waiting->value)->toBe('waiting')
        ->and(GameStatus::Playing->value)->toBe('playing')
        ->and(GameStatus::Finished->value)->toBe('finished');
});
