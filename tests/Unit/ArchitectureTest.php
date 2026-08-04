<?php

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

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

arch('broadcast events are queued')
    ->expect('App\Events')
    ->toImplement(ShouldBroadcast::class)
    ->not->toImplement(ShouldBroadcastNow::class);
