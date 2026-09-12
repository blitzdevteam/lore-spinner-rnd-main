# Main Story Gameplay Turn Delivery Fix

## Revert first

The implementation described in this document is commit:

```bash
f3b639b01cb872c093705eb46c8c4b81396b1229
```

If deployment testing shows that the fix is not suitable, revert the implementation
commit with:

```bash
git revert f3b639b01cb872c093705eb46c8c4b81396b1229
```

Do not use `git reset --hard` to undo this work. The repository contains unrelated
local R&D files and changes that must not be discarded.

The documentation commit is separate from the implementation commit. Reverting the
implementation commit leaves this review document available for comparison.

---

## 1. Problem reported

During normal single-player story mode, especially after ten or more turns, a player
could:

1. Select a suggested choice or submit a freeform action.
2. See the narration begin streaming.
3. Watch the narration finish.
4. Have the narration disappear, returning the page to the previous unanswered
   choice/input state.
5. Submit the same move again and see the narration reappear immediately.

There were also reports of Nginx/PHP-FPM errors similar to:

```text
upstream timed out (110: Connection timed out) while reading upstream
upstream: fastcgi://127.0.0.1:9000
POST /user/games/{game}/prompt
```

These symptoms can look like one bug, but they can come from two related failure
paths:

- The LLM turn is committed, but the streaming HTTP connection or a later page
  refresh fails. The player sees an incomplete or stale UI even though the database
  contains the narration.
- The LLM request genuinely fails before committing. The temporary client-side
  streaming card is removed and the player needs a visible retry path.

The most important behavioral invariant is:

> A player's committed move must not depend on the browser keeping an HTTP request
> open, and a disconnected client must be able to recover the committed narration
> from the server.

---

## 2. Repository and beta context

Before this change, local `main` already contained the beta merge and the earlier
turn-loss fixes:

```text
f37dc72b Merge latest beta features into local R&D sync branch.
```

The earlier fix commit `3b27383` was present in the branch. It added:

- Local prompt merging after Inertia reloads.
- Visible stream errors.
- Retry handling.
- ULID ordering.
- Better source-prompt selection.

The follow-up `965525e` was also present. It removed a `shouldAnimate` toggle that
remounted the completed card and caused a separate vanish-then-rerender effect.

Those fixes reduced the problem but did not remove the fundamental coupling between:

```text
browser POST
  → PHP-FPM request
  → provider streaming connection
  → database commit
  → final HTTP done event
```

The current implementation keeps the completed turn in the database and moves the
player-facing delivery to a private broadcast channel with status polling.

---

## 3. Previous architecture

### 3.1 Frontend submitted a long-lived NDJSON request

`resources/js/pages/User/Games/Show.vue` sent every move with:

```ts
headers: {
    Accept: 'application/x-ndjson',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-XSRF-TOKEN': csrfToken(),
},
body: JSON.stringify({
    prompt,
    prompt_id: options.promptId,
    suggestion_index: options.suggestionIndex,
    canonical_choice_id: options.canonicalChoiceId ?? null,
    canonical_path_label: options.canonicalPathLabel ?? null,
    stream: true,
}),
```

The browser waited for `fetch()` to finish reading the entire response body.

### 3.2 The controller selected the streaming path

`PromptController::store()` detected the NDJSON request and entered
`streamTurn()`:

```php
if ($this->wantsStreamingTurn($request)) {
    Cache::put($this->processGameTurn->pendingKey($game->id), $sourcePromptId, 180);
    Cache::forget($this->processGameTurn->errorKey($game->id));
    $deferred = true;

    return $this->streamTurn(
        request: $request,
        game: $game,
        lock: $turnLock,
        lockName: $lockName,
        sourcePromptId: $sourcePromptId,
        storedPromptValue: $storedPromptValue,
        playerAction: $playerAction,
        explicitBranch: $explicitBranch,
        userId: $user->id,
    );
}
```

### 3.3 The stream ran the AI synchronously inside the HTTP request

`streamTurn()` called:

```php
$responsePrompt = $this->processGameTurn->handleStreaming(
    gameId: (string) $game->id,
    sourcePromptId: $sourcePromptId,
    storedPromptValue: $storedPromptValue,
    playerAction: $playerAction,
    explicitBranch: $explicitBranch,
    userId: $userId,
    onResponseDelta: function (string $delta) use ($send): void {
        $send('delta', ['text' => $delta]);
    },
);
```

That path performed all of the following before returning:

1. Loaded story and adaptation context.
2. Built the runtime system prompt.
3. Built conversation history.
4. Called the AI provider.
5. Parsed the structured tool response.
6. Updated world state and game state.
7. Committed the source prompt and response prompt.
8. Sent the final `done` NDJSON event.

### 3.4 The provider stream could be silent

`ChaosEngineService::callAgentStreaming()` iterated over the provider stream:

```php
foreach ($request->asStream() as $event) {
    if ($event instanceof ToolCallDeltaEvent) {
        $toolJson .= $event->delta;
        $delta = $responseExtractor->push($event->delta);

        if ($delta !== '') {
            $onResponseDelta($delta);
        }
    }
}
```

The configured provider timeout was 150 seconds:

```php
->withClientOptions(['timeout' => 150, 'connect_timeout' => 10])
```

If the provider was quiet for longer than the upstream infrastructure allowed,
Nginx could terminate the connection while PHP-FPM was still waiting for the
provider.

The exact timeout behavior depends on the infrastructure. `fastcgi_read_timeout`
is generally an inactivity timeout between upstream reads, not simply a total
request-duration limit. The growing system prompt and conversation history after
many turns increase the time before useful provider output appears.

### 3.5 Completion triggered a second, uncoordinated page reload

After receiving `done`, the previous frontend code did this:

```ts
router.reload({ only: ['game', 'usage'] });
```

The reload was fire-and-forget. An older or stale reload could arrive after a
newer local commit and replace the prompt list with an older server snapshot.
That was the direct explanation for the “narration appears, then disappears, then
reappears instantly when I repeat the move” behavior.

---

## 4. New architecture

The new single-player turn lifecycle is:

```text
Player selects/types a move
          │
          ▼
POST /user/games/{game}/prompt
          │
          ├── validates current source prompt
          ├── sets pending state
          ├── dispatches deferred runner
          └── returns HTTP 202 immediately
                    │
                    ▼
          RunDeferredGameTurnAction
                    │
                    ├── broadcast ready
                    ├── run ProcessGameTurnAction
                    ├── broadcast coalesced narration deltas
                    ├── commit/read response prompt
                    └── broadcast done or error
                    │
                    ▼
          Frontend receives Echo event
          or recovers through turn-status polling
```

The websocket event improves perceived responsiveness. The status endpoint is the
authoritative fallback. The database response prompt is the durable completion
record.

The implementation deliberately does not require the socket to be available:

```text
Socket event = fast live delivery
Status endpoint = recovery delivery
Database row = authoritative completion
```

---

## 5. Files changed

### New files

```text
app/Actions/Game/RunDeferredGameTurnAction.php
app/Events/GameNarrationStreamed.php
app/Http/Controllers/User/Game/TurnStatusController.php
tests/Feature/User/GameChannelAuthorizationTest.php
tests/Feature/User/GamePromptTurnAcceptanceTest.php
tests/Feature/User/GameTurnStatusTest.php
tests/Feature/User/RunDeferredGameTurnActionTest.php
tests/Unit/GameNarrationStreamedEventTest.php
```

### Modified files

```text
app/Actions/Game/ProcessGameTurnAction.php
app/Http/Controllers/User/Game/PromptController.php
resources/js/pages/User/Games/Show.vue
resources/js/types/index.d.ts
routes/channels.php
routes/routes/user.php
```

No push was performed. The implementation commit is local on `main`.

---

## 6. Backend changes

### 6.1 `GameNarrationStreamed`

File:

```text
app/Events/GameNarrationStreamed.php
```

This event mirrors the existing multiplayer event contract:

```text
ready → delta* → done
ready → error
```

Event phases:

```php
public const string PHASE_READY = 'ready';
public const string PHASE_DELTA = 'delta';
public const string PHASE_DONE = 'done';
public const string PHASE_ERROR = 'error';
```

It broadcasts to:

```php
new PrivateChannel('game.'.$gameId)
```

The event name is:

```text
game.narration.streamed
```

The `done` event includes:

- The source prompt ID.
- The stored player action.
- The committed response prompt.
- The small game-state slice required by the play page:
  - `current_session_number`
  - `current_session_complete`
  - `uses_playable_moments`

The event intentionally does not send the whole `GameResource`. This avoids
reintroducing a full page synchronization request after every turn.

The event manually mirrors `PromptResource` because a broadcast does not have the
same request/resource lifecycle as an HTTP response:

```php
public static function promptPayload(Prompt $prompt): array
{
    return [
        'id' => (string) $prompt->id,
        'game_id' => (string) $prompt->game_id,
        'session_number' => $prompt->session_number,
        'prompt' => $prompt->prompt ?? '',
        'response' => (string) $prompt->response,
        'choices' => collect((array) ($prompt->choices ?? []))
            ->map(/* normalize string and structured choices */)
            ->values()
            ->all(),
        'created_at' => $prompt->created_at?->toISOString(),
        'updated_at' => $prompt->updated_at?->toISOString(),
    ];
}
```

### 6.2 Private channel authorization

File:

```text
routes/channels.php
```

Added:

```php
Broadcast::channel('game.{gameId}', function (User $user, string $gameId): bool {
    $ownerId = Game::query()->whereKey($gameId)->value('user_id');

    return $ownerId !== null && (int) $ownerId === (int) $user->id;
}, ['guards' => ['user']]);
```

Only the owning user can subscribe to their game’s narration channel. This prevents
another authenticated user from receiving player actions or narration deltas.

### 6.3 `RunDeferredGameTurnAction`

File:

```text
app/Actions/Game/RunDeferredGameTurnAction.php
```

This class owns the post-acceptance lifecycle:

```php
GameNarrationStreamed::dispatch(
    $gameId,
    $sourcePromptId,
    GameNarrationStreamed::PHASE_READY,
    null,
    $storedPromptValue,
);
```

It calls `ProcessGameTurnAction::handle()` with an `onResponseDelta` callback.
Deltas are buffered until at least 48 characters have accumulated:

```php
$buffer .= $delta;

if (mb_strlen($buffer) >= self::DELTA_FLUSH_CHARS) {
    $flush();
}
```

This prevents a provider response from generating hundreds of tiny websocket
events.

After processing, the runner re-reads the response prompt by:

```php
->where('game_id', $gameId)
->where('source_prompt_id', $sourcePromptId)
->orderByDesc('id')
->first();
```

This re-read is important. If the database commit succeeded but an error happened
after the commit, the client must receive `done`, not `error`.

If no response row exists, the runner sends:

```php
GameNarrationStreamed::PHASE_ERROR
```

with the cached error message or a safe default.

### 6.4 `ProcessGameTurnAction`

File:

```text
app/Actions/Game/ProcessGameTurnAction.php
```

`handle()` now accepts an optional delta callback and returns the committed
`Prompt`:

```php
public function handle(
    string $gameId,
    string $sourcePromptId,
    string $storedPromptValue,
    string $playerAction,
    ?array $explicitBranch,
    int $userId,
    ?callable $onResponseDelta = null,
): ?Prompt
```

This allows both of these modes:

```php
// Deferred live delivery:
$action->handle(..., onResponseDelta: $broadcastDelta);

// Deferred non-streaming compatibility:
$action->handle(...);
```

The existing transaction and idempotency behavior remain in the action. The
existing unique database key on `prompts(game_id, source_prompt_id)` prevents
multiple response rows for the same source prompt.

### 6.5 `PromptController`

File:

```text
app/Http/Controllers/User/Game/PromptController.php
```

The controller now distinguishes:

```text
Accept: application/json
Accept: application/x-ndjson
Inertia/browser form request
```

The new main-story frontend uses JSON. A valid JSON request:

1. Validates the source prompt.
2. Validates the selected choice/canonical branch.
3. Stores the pending cache marker.
4. Dispatches `RunDeferredGameTurnAction` using `afterResponse()`.
5. Returns HTTP `202`.

The response shape is:

```json
{
  "accepted": true,
  "game_id": "...",
  "source_prompt_id": "...",
  "source_prompt_value": "...",
  "channel": "game...."
}
```

The controller keeps the previous NDJSON branch temporarily so a browser running
an older deployed asset is not immediately stranded during a rolling deployment.
The new frontend does not use that branch.

The controller also returns explicit JSON conflict responses:

```text
409 in_progress
409 out_of_turn
409 already_answered
422 invalid
```

When the same move is submitted after the first move already committed, the
controller returns the existing response prompt:

```php
$existingResponse = $game->prompts()
    ->where('source_prompt_id', $sourcePromptId)
    ->orderByDesc('id')
    ->first();
```

This changes the old “repeat the move to make the narration come back” behavior
from an accidental recovery into an explicit idempotent response.

### 6.6 `TurnStatusController`

File:

```text
app/Http/Controllers/User/Game/TurnStatusController.php
```

Route:

```php
Route::get('games/{game}/turn-status', User\Game\TurnStatusController::class)
    ->name('games.turn-status');
```

Example request:

```text
GET /user/games/{game}/turn-status?source_prompt_id={sourcePromptId}
```

The response reports:

```json
{
  "pending": true,
  "pending_source_prompt_id": "...",
  "error": null,
  "source_prompt_id": "...",
  "source_prompt_value": null,
  "prompt": null,
  "game": {
    "current_session_number": 1,
    "current_session_complete": false,
    "uses_playable_moments": false
  }
}
```

After commit:

```json
{
  "pending": false,
  "error": null,
  "source_prompt_id": "...",
  "source_prompt_value": "I open the door.",
  "prompt": {
    "id": "...",
    "response": "<p>...</p>",
    "choices": []
  },
  "game": {
    "current_session_number": 1,
    "current_session_complete": false,
    "uses_playable_moments": true
  }
}
```

The controller verifies ownership directly:

```php
abort_unless((int) $game->user_id === (int) $user->id, 403);
```

The status endpoint is the authoritative recovery mechanism when Echo is
disconnected, unavailable, or misses the `done` event.

### 6.7 Routes

Files:

```text
routes/routes/user.php
routes/channels.php
```

Added:

```text
GET user/games/{game}/turn-status
```

Added private channel:

```text
private-game.{gameId}
```

---

## 7. Frontend changes

### 7.1 `Show.vue` no longer keeps the POST open for narration

File:

```text
resources/js/pages/User/Games/Show.vue
```

The old request used:

```ts
Accept: 'application/x-ndjson'
stream: true
```

The new request uses:

```ts
Accept: 'application/json'
```

It expects `202 Accepted`, then waits for the private Echo event or the status
poll. The browser POST is no longer the delivery mechanism for narration.

### 7.2 One active turn state

The frontend now tracks one active turn:

```ts
type ActiveTurn = {
    sourcePromptId: string;
    streamingPromptId: string;
    prompt: string;
    committed: boolean;
    settled: boolean;
    uncertain: boolean;
    startedAt: number;
    emptyStatusPolls: number;
    anchoredToStreamStart: boolean;
    pollTimer: ReturnType<typeof setTimeout> | null;
};
```

This prevents multiple simultaneous local turn states from competing with each
other and makes every completion path idempotent.

### 7.3 Immediate local action echo

The selected choice or typed action still appears immediately using
`pendingSelection`. This preserves the original UX while narration is pending.

### 7.4 Echo subscription

The page subscribes to:

```ts
useEcho<GameNarrationSignal>(
    `game.${props.game.id}`,
    '.game.narration.streamed',
    handleNarrationSignal,
);
```

The event handler:

- Ignores events for another game.
- Ignores events for another source prompt.
- Appends delta text to the temporary streaming card.
- Commits the response prompt on `done`.
- Displays a retryable error on `error`.

### 7.5 Status polling fallback

The page polls:

```text
GET /user/games/{game}/turn-status?source_prompt_id=...
```

Polling is not a second source of truth. It simply asks the database whether the
response prompt exists.

The poller:

- Runs every 2 seconds.
- Has a 240-second maximum.
- Immediately adopts a committed response prompt.
- Stops when the socket delivers `done`.
- Continues through transient network errors.
- Does not fail on one empty response.
- Requires two empty non-pending status responses before showing failure.

This avoids declaring a healthy turn failed during the small interval between
request acceptance and the pending marker becoming observable.

### 7.6 No post-turn `router.reload`

The previous completion path ended with:

```ts
router.reload({ only: ['game', 'usage'] });
```

That reload was removed from turn completion. The `done` event carries the small
game-state slice needed to update:

```ts
gameState.value = { ...nextGameState };
```

This removes the stale-reload race that could overwrite locally committed
narration with an older server snapshot.

### 7.7 Preserve already-visible streamed text

`commitTurn()` checks whether the temporary card already contains visible text:

```ts
const hadLiveText = hasVisibleStreamedText(streamed?.response ?? '');
```

If text already streamed, the card is committed without restarting the
typewriter. If no live text arrived, the committed card is allowed to animate
normally. This avoids the empty-card remount and vanish-then-rerender behavior.

### 7.8 Refresh/reload recovery

If the player reloads the page while a turn is pending, `Show.vue` finds the
unanswered source prompt and calls:

```ts
resumeTurn(awaiting.id);
```

That resumes status polling against the source prompt. The player does not need
to resubmit the action.

---

## 8. TypeScript contracts

File:

```text
resources/js/types/index.d.ts
```

Added types:

```ts
export interface GameNarrationSignal {
    game_id: string;
    source_prompt_id: string;
    phase: 'ready' | 'delta' | 'done' | 'error';
    text?: string;
    source_prompt_value?: string;
    prompt?: PromptInterface;
    game?: GameTurnStateInterface;
    message?: string;
}
```

The status response and accepted/rejected response are also typed so the
frontend cannot silently treat an error payload as a committed prompt.

---

## 9. Tests added

### `GameNarrationStreamedEventTest`

Verifies:

- Private channel name.
- Event name.
- Ready/delta payloads.
- Completed prompt serialization.
- Game state serialization.
- Error payloads.

### `GamePromptTurnAcceptanceTest`

Verifies:

- JSON turn requests return `202`.
- Pending state is written before the response returns.
- The deferred runner receives the exact stored/player action values.
- Continue moves are translated correctly.
- Duplicate submissions return the committed response.
- Out-of-turn moves are rejected.
- Concurrent submissions are rejected as `in_progress`.
- Plain Inertia/form posts still receive redirects.

### `GameTurnStatusTest`

Verifies:

- Pending response shape.
- Committed response shape.
- Failure response shape.
- Empty-source lookup behavior.
- Unauthorized users receive `403`.

### `RunDeferredGameTurnActionTest`

Verifies:

- Ready → delta → done lifecycle.
- Delta coalescing.
- Committed game state in the done event.
- Error event when no response committed.
- Done event when a response row exists despite a later exception.

### `GameChannelAuthorizationTest`

Verifies:

- The owner can authorize the private game channel.
- Other users cannot authorize it.
- Missing games cannot authorize it.

## Verification performed

The focused tests passed:

```text
17 tests
80 assertions
```

The frontend verification passed:

```text
Vite production build: passed
Vue type-check for changed files: passed
ESLint: passed
Laravel Pint: passed
```

The full unit-suite run was started but interrupted because it was taking too
long in the local temporary PostgreSQL environment. It was not pushed and no
deployment was performed.

---

## 10. Important current limitation

This commit removes the long-lived narration connection from the player's POST,
but the deferred runner currently uses Laravel's `afterResponse()` mechanism:

```php
dispatch(static function () use (...): void {
    app(RunDeferredGameTurnAction::class)->handle(...);
})->afterResponse();
```

That means:

- The browser receives the HTTP response before narration completes.
- Nginx does not need to wait for the model response.
- Echo and polling can recover the completed result.
- However, the work still executes in the PHP-FPM lifecycle after the response
  has been sent.

For maximum production resilience, the next hardening step is a real queued job
on a dedicated narration queue. That would protect against:

- PHP-FPM `request_terminate_timeout`.
- Worker recycling.
- Deploys during generation.
- Long-running narrations occupying all web workers.

The current commit is therefore a substantial delivery and recovery fix, but
deployment verification must explicitly test:

1. A normal choice turn.
2. A freeform turn.
3. A turn with a long provider delay.
4. A browser refresh while narration is pending.
5. A websocket-disabled or disconnected browser.
6. A repeated submit after the first turn has already committed.
7. Ten or more consecutive turns.

---

## 11. Deployment review checklist

Do not push this commit automatically. Before deployment:

### Backend

- Confirm `routes/channels.php` is loaded by `bootstrap/app.php`.
- Confirm the deployed environment uses the `user` guard for private channel auth.
- Confirm Reverb is enabled and reachable from the deployed frontend.
- Confirm the normal queue/worker configuration does not interfere with
  `afterResponse()` execution.
- Confirm narration logs include:
  - `game.turn`
  - `game.turn_failed`
  - `game.turn_deferred_failed`

### Frontend

- Confirm the compiled asset includes:
  - `game.narration.streamed`
  - `/turn-status`
  - `202`
  - `resumeTurn`
- Confirm old browser bundles can still finish because the NDJSON compatibility
  path remains temporarily available.

### Manual QA

For each title:

```text
Turn 1: freeform
Turn 2: suggested choice
Turns 3–10: mixed choice and freeform
Turns 11+: continue until at least 15 turns
```

Record:

- Whether narration remained visible after completion.
- Whether the selected action remained attached to the correct card.
- Whether refreshing during generation recovered the narration.
- Whether repeating an already-committed move produced an immediate existing-row
  recovery instead of a second narration.
- Whether session completion controls appeared correctly.
- Whether the active playable moment advanced correctly.

---

## 12. Commit history for this work

Implementation:

```text
f3b639b0 fix gameplay turn delivery and recovery
```

Documentation:

```text
Added after the implementation commit so the exact revert target is available
at the top of this file.
```

Neither commit is pushed by this work.
