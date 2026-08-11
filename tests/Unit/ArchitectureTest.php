<?php

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;

arch('uses safe PHP constructs')->preset()->php();
arch('avoids common security hazards')->preset()->security();

arch('controllers follow Laravel naming conventions')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('domain actions are classes')
    ->expect('App\Actions')
    ->toBeClasses();

arch('models do not depend on controllers')
    ->expect('App\Models')
    ->not->toUse('App\Http\Controllers');

arch('realtime events broadcast immediately and do not break commands when the transport is unavailable')
    ->expect('App\Events')
    ->toImplement(ShouldBroadcastNow::class)
    ->toImplement(ShouldRescue::class);
