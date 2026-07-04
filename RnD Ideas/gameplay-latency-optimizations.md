# Gameplay Latency Optimizations — `claude-sonnet-4-6`

> Context: `callAgent()` in `ChaosEngineService` runs synchronously inside the HTTP request.
> Cloudflare sits upstream with a 100s timeout. The timeout was dropped to 55s (Jun 29 2026)
> and server-side retries were removed to prevent 504s. These are the remaining improvements.

---

## 1. Prompt Caching — biggest win

**What:** Anthropic caches any prompt prefix > 1024 tokens for 5 minutes (refreshed on each use).
Your system prompt is the runtime narrator template (~478 lines) + session context + world rules +
IP trimming data. Almost all of it is static per game turn — only `world_state` and conversation
history change. Caching the static prefix means Anthropic doesn't re-process it on every turn.

**Impact:** ~40–60% latency reduction on turns 2+. Also ~90% cost reduction on cached input tokens.

**How:** Add `cache_control: {type: "ephemeral"}` to the system prompt block in the Prism request,
or via `withProviderOptions(['cache_control' => 'ephemeral'])`. The static system prompt prefix
must be identical across turns for the cache to hit.

**Effort:** Low — 2–3 lines in `callAgent()`.

---

## 2. Reduce `max_tokens` to realistic output size

**What:** `max_tokens` is currently set to `64_000` for Anthropic calls. Anthropic's infrastructure
allocates output buffer capacity based on this value — a 64k reservation on a call that will
produce ~500–1500 tokens delays scheduling.

**Impact:** Medium — faster job scheduling on Anthropic's side, especially under load.

**How:** Set `max_tokens` to `2000` (or `3000` as a safe ceiling for verbose turns with long prose
+ 3 choices + state_delta). Validate with the longest real game turn output first.

**Effort:** Low — 1 line.

---

## 3. Explicitly disable adaptive thinking

**What:** `claude-sonnet-4-6` uses adaptive thinking by default — it internally decides whether to
reason through the prompt. For a game narration turn where the runtime narrator prompt does 80% of
the work, reasoning adds latency with no quality benefit. The model should execute the prompt, not
deliberate over it.

**Impact:** Low–Medium — removes the adaptive thinking overhead on turns where the model chooses
to think. Guarantees fast TTFT.

**How:** Pass `thinking: {type: "disabled"}` via Prism's `withProviderOptions()`. Note: verify
this is supported in the Prism version on production before deploying.

**Effort:** Low — 1–2 lines.

---

## 4. Streaming (transformative UX — medium effort)

**What:** Switch `asStructured()` to streaming via SSE. The player sees narration prose appearing
word-by-word within 1–2s of submitting their choice instead of waiting 15–30s for the full JSON
response to complete. Structured fields (choices, state_delta, session_complete) arrive at the end
of the stream and are committed to DB once complete.

**Impact:** Transformative — eliminates the perceived wait entirely. Cloudflare 504 risk also
disappears because TTFT is < 5s (well under any timeout), and the stream connection uses chunked
transfer which has different timeout semantics.

**Considerations:**
- Requires a frontend change: Inertia page needs to poll or receive SSE events.
- DB writes (world_state, prompts, game update) happen at stream end, not start.
- Prism supports Anthropic streaming via `->asStream()` on text requests; structured streaming
  requires reading the text stream and parsing the JSON object as it accumulates.
- Partial render approach: stream the `response` prose field first (model outputs it first in the
  schema), show choices once the full JSON is parseable.

**Effort:** High — frontend + backend changes. Worth planning as a v2 feature.

---

## Priority Order (AI latency)

| # | Option | Effort | Impact |
|---|---|---|---|
| 1 | Prompt caching | Low | High |
| 2 | `max_tokens: 2000` ✅ done | Low | Medium |
| 3 | Disable adaptive thinking | Low | Low–Medium |
| 4 | Streaming | High | Transformative |

---

## DB & Memory Inefficiencies (per game turn)

These don't affect Anthropic latency but they add unnecessary overhead to every player turn request.

---

### 5. Conversation history — no DB limit on query

**File:** `app/Http/Controllers/User/Game/PromptController.php` → `buildConversationHistory()`

**What:** The DB query loads all prompts for the current session (`->oldest()->get()` — no LIMIT). Then `array_slice($history, -12)` discards most of them at the call site. On turn 15 this loads and hydrates 15 rows just to use 12. Gets worse the longer a session runs.

**Fix:** Add `->latest()->limit(12)->get()` to the query and reverse the collection, or `->orderByDesc()->limit(12)->get()` then reverse. Move the slice to the query, not PHP.

**Effort:** 1 line.

---

### 6. Full `sessionAdaptations` eager load — 5 unused heavy JSON columns

**File:** `app/Http/Controllers/User/Game/PromptController.php` line 38

**What:** Every turn loads `adaptation.sessionAdaptations` with all columns — including `session_architecture`, `session_choice_design`, `choice_consequence_map`, `session_close_design`, and `editorial_verification`. These are large JSON columns (each can be 10–50KB) written during adaptation but never read during gameplay. The relationship loads all 6 session rows × all columns just to call `->firstWhere('session_number', $n)` and `->count()`.

**Fix:** Add a `select()` constraint to the eager load:
```php
with(['adaptation.sessionAdaptations' => fn ($q) => $q->select([
    'id', 'story_adaptation_id', 'session_number',
    'runtime_narrator_prompt', 'entry_point_diagnosis',
])])
```
This still loads all 6 rows (needed for `->count()` to get `total_sessions`) but drops the 5 heavy adaptation-only columns.

**Effort:** 3 lines.

---

### 7. Events JOIN query fires on every player turn

**File:** `app/Services/ChaosEngineService.php` → `loadSessionContext()` lines 128–156

**What:** A JOIN across `events` and `chapters` runs on every call to `loadSessionContext()`, which is called on every game turn in `PromptController::store()`. The session events never change during a playthrough — they're static per story session. The result is re-fetched from DB on every turn unnecessarily.

**Fix:** Cache the result of `loadSessionContext()` in Laravel cache keyed by `game_session_context:{story_id}:{session_number}`. TTL of 1 hour (or invalidate on adaptation re-run). Saves one JOIN query per turn.

**Effort:** ~10 lines wrapping the existing method body.

---

### 8. Symbolic memory grows unbounded

**File:** `app/Services/ChaosEngineService.php` → `appendMemory()` line 470

**What:** `appendMemory()` concatenates `symbolic_memory_update` strings with newlines indefinitely. On a long playthrough (30+ turns), the symbolic memory block injected at `[SYMBOLIC_MEMORY_INJECTION_POINT]` into every system prompt can become a significant chunk of tokens — hundreds of tokens per turn, growing forever.

**Fix:** Cap at a rolling window. Two options:
- Hard char cap (e.g. keep the last 1500 chars): simplest, no information loss on recent turns.
- Sentence count cap (keep last N sentences): cleaner but requires parsing.

The cap only trims old history — recent symbolic memory (which matters most to the narrator) is always preserved.

**Effort:** 3–5 lines.

---

### 9. HTML stripped from response on every history rebuild

**File:** `PromptController::buildConversationHistory()` line 151 — `$this->engine->stripHtml($p->response)`

**What:** Narrator responses are stored with HTML tags (`<p>`, `<em>`, `<strong>`). On every turn, `stripHtml()` is called on every history entry to produce plain text for the conversation context. For 12 history entries this is 12 `strip_tags()` + `html_entity_decode()` calls per turn, every turn. Minor overhead individually, but happens every single turn forever.

**Fix:** Store the stripped plain text version at write time in a separate `response_plain` column on `game_prompts`, populated once in `PromptController::store()`. Read that on history rebuild instead of stripping live. Requires a migration.

**Effort:** Medium — migration + write path change + read path change. Lower priority than the others.

---

## Full Priority Order

| # | Option | Effort | Impact | Done |
|---|---|---|---|---|
| 1 | Prompt caching | Low | High | — |
| 2 | `max_tokens: 2500` | Low | Medium | ✅ |
| 3 | Disable adaptive thinking | Low | Low–Medium | — |
| 4 | DB query limit on conversation history | 1 line | Low-medium | — |
| 5 | `select()` on sessionAdaptations load | 3 lines | Medium | — |
| 6 | Cache `loadSessionContext()` per session | ~10 lines | Medium | — |
| 7 | Cap symbolic memory at ~1500 chars | 3 lines | Medium (long-term) | — |
| 8 | Store plain response at write time | Medium | Low | — |
| 9 | Streaming | High | Transformative | — |

---

*Added Jun 29 2026 after Cloudflare 504 incident during Thomas Wittmer gameplay session.*
