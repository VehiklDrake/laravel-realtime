<?php

use Inertia\Testing\AssertableInertia as Assert;

it('shows the game landing page', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('defaults.rounds')
            ->has('defaults.roundDuration'));
});
