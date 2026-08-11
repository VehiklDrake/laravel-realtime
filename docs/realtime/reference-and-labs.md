# Realtime reference and labs

## Active event catalog

All active events publish to the presence channel `game.{game_id}` and define a custom name, so the Echo listener includes a leading `.`.

| PHP event        | Dispatched by            | Payload summary                          | Frontend reaction                 |
| ---------------- | ------------------------ | ---------------------------------------- | --------------------------------- |
| `PlayerJoined`   | `CreateGame`, `JoinGame` | public player fields                     | refresh snapshot                  |
| `PlayerLeft`     | `LeaveGame`              | deleted player ID and name               | refresh snapshot                  |
| `GameStarted`    | `StartGame`              | status and current round                 | refresh snapshot                  |
| `RoundStarted`   | `StartRound`             | round metadata; no secret word           | refresh snapshot                  |
| `RoundEnded`     | `FinishRound`            | round number, revealed word, scores      | refresh snapshot                  |
| `GameFinished`   | `FinishRound`            | final ordered players/scores             | refresh snapshot                  |
| `ChatMessage`    | `SubmitGuess`            | non-correct message                      | append if message ID is new       |
| `CorrectGuess`   | `SubmitGuess`            | scorer, points, redacted success message | append and refresh                |
| `DrawingStrokes` | `RecordDrawing`          | round ID and a batch of strokes          | append current-round peer strokes |
| `CanvasCleared`  | `ClearDrawing`           | round ID                                 | clear current-round canvas        |

Source: [app/Events](../../app/Events) and the subscriptions in [Room.vue](../../resources/js/pages/Games/Room.vue).

The singular `DrawingStroke` event is not active; see the caveat in [README](README.md#important-repository-caveat).

## Configuration map

Realtime configuration has three perspectives. Similar names do not mean the same thing.

| Variables                                                    | Read by                                | Meaning                                                 |
| ------------------------------------------------------------ | -------------------------------------- | ------------------------------------------------------- |
| `BROADCAST_CONNECTION=reverb`                                | Laravel HTTP/queue process             | select the server-side broadcast driver                 |
| `QUEUE_CONNECTION=database`                                  | Laravel and worker                     | queue broadcasts and delayed round jobs in the database |
| `REVERB_APP_ID`                                              | Laravel + Reverb                       | application identifier                                  |
| `REVERB_APP_KEY`                                             | Laravel + Reverb + browser via `VITE_` | public application key                                  |
| `REVERB_APP_SECRET`                                          | Laravel + Reverb only                  | secret used to authenticate publish/auth operations     |
| `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT`                   | Reverb process                         | local interface and port on which Reverb listens        |
| `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`                | Laravel broadcaster                    | public/reachable address to which Laravel publishes     |
| `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME` | browser bundle                         | address at which browsers open WebSockets               |
| `REVERB_VERIFY_TLS`                                          | server-side Reverb HTTP client         | whether Laravel verifies the remote TLS certificate     |

The default local shape is usually:

```text
Reverb binds:       0.0.0.0:8080
Laravel publishes:  http://localhost:8080
Browser connects:   ws://localhost:8080
```

In production those may diverge:

```text
Reverb binds:       0.0.0.0:8080 (private)
Reverse proxy:      wss://ws.example.com:443 (public)
Laravel publishes:  https://ws.example.com:443
Browser connects:   wss://ws.example.com:443
```

See [.env.example](../../.env.example), [config/broadcasting.php](../../config/broadcasting.php), and [config/reverb.php](../../config/reverb.php).

### Production settings to revisit

The current Reverb configuration has `allowed_origins => ['*']`, which is convenient locally. Production should list the actual application origins. Also review connection/message limits, rate limiting, TLS termination, process supervision, file descriptor limits, and Redis-backed horizontal scaling before treating the default configuration as production-ready.

## Runtime commands

Laravel 13's `dev` command aggregates the default PHP server, queue listener, logs, and Vite process; the Reverb package registers `reverb:start` into the same process group. The repository's Composer script delegates to it:

```bash
composer run dev
```

When the application itself is already served by Herd, the realtime pieces can be observed independently in separate terminals:

```bash
npm run dev
php artisan queue:work --tries=1
php artisan reverb:start --debug
```

Useful inspection commands:

```bash
php artisan channel:list
php artisan route:list --path=broadcasting -vv
php artisan queue:failed
php artisan reverb:restart
```

Reverb and queue workers are long-running processes. Restart them after code or configuration changes. `reverb:restart` gracefully terminates connections so a process manager can restart the server.

## Failure diagnosis by layer

| Observation                                           | Likely layer                                     | First checks                                                                                        |
| ----------------------------------------------------- | ------------------------------------------------ | --------------------------------------------------------------------------------------------------- |
| connection badge is `failed`                          | browser -> Reverb transport                      | `VITE_REVERB_*`, scheme/port, Reverb process, browser console, reverse proxy `/app` support         |
| WebSocket connects but presence subscription gets 403 | authorization HTTP request                       | session cookie, `/broadcasting/auth`, middleware, stored player token, `presence-game.{id}` parsing |
| HTTP mutation succeeds, sender updates, peers do not  | queue or publish path                            | queue worker, failed jobs, `BROADCAST_CONNECTION`, Reverb debug output/credentials                  |
| peers update only every 15 seconds                    | broadcasts unavailable but snapshot path healthy | queue worker and Reverb publish path                                                                |
| countdown reaches zero but round does not change      | delayed queue job                                | queue worker, `jobs` table, `EndRound` failure logs                                                 |
| only drawings are stale                               | batching/retry or event filter                   | drawing request responses, `round_id`, request validation, `DrawingStrokes` queue job               |
| server emits event but listener never fires           | event naming                                     | leading `.` for every `broadcastAs()` name                                                          |
| works over `ws`, fails over `wss`                     | TLS/proxy configuration                          | scheme, certificate, proxy Upgrade/Connection headers, public vs bind host                          |
| works after refresh but not live                      | transport or subscription                        | presence auth response, Echo connection status, Reverb debug stream                                 |

## Tests as executable documentation

The most instructive tests are:

| Test                                                                                      | What it proves                                                                   |
| ----------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| [BroadcastAuthorizationTest.php](../../tests/Feature/Game/BroadcastAuthorizationTest.php) | session players can authorize presence; visitors cannot; SSR uses null Echo      |
| [DrawingTest.php](../../tests/Feature/Game/DrawingTest.php)                               | batches persist before one queued broadcast; only drawer/current round may write |
| [SubmitGuessTest.php](../../tests/Feature/Game/SubmitGuessTest.php)                       | guesses persist, broadcast asynchronously, score once, and never leak the word   |
| [RoundLifecycleTest.php](../../tests/Feature/Game/RoundLifecycleTest.php)                 | delayed lifecycle is idempotent and advances/finalizes correctly                 |
| [ArchitectureTest.php](../../tests/Unit/ArchitectureTest.php)                             | application events are intended to be queued rather than synchronous             |

Run the active-flow feature tests:

```bash
php artisan test --compact \
  tests/Feature/Game/BroadcastAuthorizationTest.php \
  tests/Feature/Game/DrawingTest.php \
  tests/Feature/Game/SubmitGuessTest.php \
  tests/Feature/Game/RoundLifecycleTest.php
```

The architecture test currently exposes the unused singular `DrawingStroke` caveat documented in the README.

## Lab 1: observe the presence authorization handshake

Goal: separate the HTTP authorization request from the WebSocket subscription.

1. Open a game room and the browser network tools.
2. Filter for `broadcasting/auth` and WebSocket traffic.
3. Inspect the auth request's `channel_name`; it should be `presence-game.{numeric-id}`.
4. Confirm the request carries the same session cookie used by ordinary game commands.
5. Inspect the successful response for `auth` and `channel_data`.
6. Open a private/incognito window without joining and reproduce the auth request; it should receive `403`.

Questions to answer:

- Why does the browser request contain the presence prefix while `Room.vue` does not?
- Why must the channel callback return `{id, name}` instead of `true`?
- Which layer proves room membership: Reverb, the middleware, or the channel callback?

Expected model: Reverb enforces the signed subscription response, but Laravel's middleware and callback decide identity and room membership.

## Lab 2: prove that the queue is part of realtime delivery

Goal: distinguish persistence from broadcast delivery.

1. Run the HTTP application and Reverb, but stop the queue worker.
2. Submit a chat message from browser A.
3. Observe that browser A receives a successful HTTP response and renders the message.
4. Observe that browser B does not receive the event immediately.
5. Inspect the queued job, then start the worker.
6. Observe browser B receive the delayed event.

Explain why the sender looked healthy: it rendered the authoritative HTTP response, not the WebSocket echo.

## Lab 3: inspect custom event naming

Goal: understand Echo's namespace formatting rule.

1. Temporarily remove the leading `.` from one local listener, such as `.ChatMessage`.
2. Send a message from another browser.
3. Observe Reverb receive/send the message while that callback does not run.
4. Restore the dot and retry.

Reason: `broadcastAs()` publishes `ChatMessage`; without the dot Echo formats the listener using its default `App.Events` namespace.

## Lab 4: measure drawing batching

Goal: connect interaction frequency to HTTP and queue frequency.

1. Draw continuously for several seconds.
2. Compare pointer events, drawing POST requests, persisted rows, and `BroadcastEvent` jobs.
3. Confirm each request contains no more than 50 strokes.
4. Confirm one request queues one `DrawingStrokes` event, not one per segment.
5. Resize the peer browser and confirm the drawing geometry remains proportional.

Explain why the local drawer must ignore its own broadcast even though the server assigned database IDs to the persisted strokes.

## Lab 5: watch snapshot repair

Goal: see incremental events and canonical snapshots cooperate.

1. Keep two browsers in one game.
2. Pause or disconnect browser B's WebSocket connection.
3. Change state from browser A.
4. Restore B's network without refreshing the page.
5. Wait for the periodic state request and observe convergence.

Then identify what is and is not recoverable from persistence:

- current roster, scores, round, drawing, and chat are recoverable;
- transient presence membership is reconstructed by rejoining, not from the database;
- an optimistic stroke that never reached the server is not recoverable after the originating page closes.

## Lab 6: test stale-round protection

Goal: understand why both client and server carry `round_id`.

1. Delay a drawing POST in browser developer tools.
2. Allow the round to advance.
3. Release the old request.
4. Observe the server return `409 Conflict`.
5. Compare that protection with the client-side `payload.round_id !== currentRound.id` event filter.

Expected conclusion: client filtering protects the local projection; only server validation protects canonical data.

## Design review prompts

Use these when extending the realtime system:

1. Is this message a command, a persisted fact, an ephemeral signal, or a cache invalidation?
2. Must the sender wait for it, or can it be queued?
3. What happens if it is delivered twice, late, or out of order?
4. What durable state lets a reconnecting client recover?
5. Does the payload expose anything a non-drawer room member must not see?
6. Does it belong on the existing game presence channel or a narrower channel?
7. Should the client apply the payload or refresh a snapshot?
8. Is the event dispatched inside a transaction, and if so, is after-commit behavior explicit?
9. What is the maximum event/request frequency and payload size?
10. Which feature test proves the dispatch, payload, authorization, and failure behavior?

## Further reading

- [Laravel 13: Broadcasting](https://laravel.com/docs/13.x/broadcasting)
- [Laravel 13: Reverb](https://laravel.com/docs/13.x/reverb)
- [Laravel 13: Queues](https://laravel.com/docs/13.x/queues)
- [Pusher Channels protocol](https://pusher.com/docs/channels/library_auth_reference/pusher-websockets-protocol/)
