<?php

use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Facades\Broadcast;

beforeEach(function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('broadcasting.connections.reverb.secret', 'test-secret');
    config()->set('broadcasting.connections.reverb.app_id', 'test-app');
    Broadcast::forgetDrivers();
    require base_path('routes/channels.php');

    $this->game = Game::factory()->create();
    $this->player = Player::factory()->for($this->game)->create();
    $this->payload = [
        'channel_name' => "presence-game.{$this->game->id}",
        'socket_id' => '1234.5678',
    ];
});

it('authorizes a session player for the room presence channel', function () {
    $this->withSession(sessionFor($this->player))
        ->post('/broadcasting/auth', $this->payload)
        ->assertSuccessful()
        ->assertJsonStructure(['auth', 'channel_data']);
});

it('rejects visitors from the room presence channel', function () {
    $this->post('/broadcasting/auth', $this->payload)->assertForbidden();
});

it('uses the authorized presence channel name in the game room client', function () {
    expect(file_get_contents(resource_path('js/pages/Games/Room.vue')))
        ->toContain('const channelName = `game.${props.game.id}`;');
});

it('uses a null Echo broadcaster while rendering on the server', function () {
    expect(file_get_contents(resource_path('js/app.ts')))
        ->toContain('if (import.meta.env.SSR)')
        ->toContain("configureEcho({ broadcaster: 'null' });")
        ->toContain("configureEcho({ broadcaster: 'reverb' });");
});

it('keeps connection state deterministic until the game room is hydrated', function () {
    expect(file_get_contents(resource_path('js/pages/Games/Room.vue')))
        ->toContain('const isClientMounted = ref(false);')
        ->toContain("isClientMounted.value ? connectionStatus.value : 'connecting'")
        ->toContain('isClientMounted.value = true;');
});

it('preserves unsynced strokes and clears a successfully submitted guess', function () {
    expect(file_get_contents(resource_path('js/components/game/DrawingCanvas.vue')))
        ->toContain('pendingStrokes.unshift(...strokes);')
        ->toContain('scheduleFlush(500);')
        ->and(file_get_contents(resource_path('js/pages/Games/Room.vue')))
        ->toContain("guessRequest.message = '';");
});
