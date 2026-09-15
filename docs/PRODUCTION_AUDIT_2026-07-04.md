# LoreSpinner — Production Readiness Audit

**Date:** 2026-07-04
**Scope:** Full app — security, scalability, runtime logic, product/business.
**Method:** Four parallel deep-dive audits across the codebase; highest-severity findings independently re-verified against source.

---

## Verdict

**Not production-ready for thousands of users — yet.** Two findings are ship-blockers you should treat as *incidents*, not backlog items:

1. **Anyone can take over any account** via the password-reset endpoint (no token, no email).
2. **Any logged-in user can read, drive paid AI turns on, and wipe any other user's game** (no ownership checks anywhere).

The *engine* — the Chaos narration loop, authored consequence maps, session-close design, voice-locked narration — is genuinely strong and differentiated. The problem is everything around it: authorization, concurrency safety, and the request architecture. The core is a moat; the surface is a sieve.

Rough current ceiling: **~100–150 concurrent active players on a single well-provisioned server** before the exact Cloudflare-504 cascade you already hit returns — this time under load instead of per-call. Local-disk storage currently blocks adding servers to compensate.

### Severity scoreboard

| Severity | Count | Examples |
|---|---|---|
| **CRITICAL** | 5 | Account-takeover reset, game IDOR, unauthenticated paid-LLM endpoints, turn-concurrency data loss, missing hot-path indexes |
| **HIGH** | ~11 | Sync AI calls holding FPM workers, `max_tokens` truncation soft-brick, no turn transaction, TTS in-request, committed API key |
| **MEDIUM** | ~15 | Stale session-opening injection, session_complete flip-back, no caching, N+1s, unsanitized `v-html` |
| **LOW** | ~10 | Mass-assignment breadth, model-slug 400s, duplicate-game race |

Full detail below. Section 6 is the product/creative recommendations you asked for.

---

## 1. CRITICAL — fix before anyone else touches production

### 1.1 Password reset = account takeover
`app/Http/Controllers/User/Authentication/ForgotPasswordController.php:22-39`

The "forgot password" flow validates only that the email *exists*, then immediately sets the new password. **No reset token, no email, no proof of ownership.**

```php
$request->validate([
    'email' => ['required', 'email', Rule::exists('users', 'email')],
    'password' => ['required', 'string', 'min:8', 'confirmed'],
]);
$user = User::where('email', ...)->first();
$user->forceFill(['password' => Hash::make(...)])->save();
```

**Exploit:** submit any known/guessed registered email + a new password → you own that account. This is trivially scriptable against a leaked email list.
**Fix:** use Laravel's built-in `Password::sendResetLink()` / tokened reset. This is a well-trodden framework path — a few hours of work.

### 1.2 IDOR on every game route — no authorization exists
`routes/routes/user.php:47-68`, all of `GameController` + `PromptController`. **Verified:** no `app/Policies` directory, no `Gate::` registration, `StorePromptRequest::authorize()` returns `true`, route-model binding is unscoped.

Any authenticated user who supplies another user's game ULID can:
- **Read** their full transcript, world state, choices (`GameController::show`).
- **Drive paid Claude turns** on their game (`begin`, `nextSession`, `PromptController::store`) — burning your LLM budget against a victim's game.
- **Wipe them out**: `POST games/{game}/reset` deletes all their prompts and resets progress (`GameController::reset:392-418`).
- **Burn TTS credits / leak audio**: `TextToSpeechController.php:17-19` checks `prompt.game_id === game.id` but never `game.user_id === auth id`.

**Fix (shared root cause with 1.4):** add a `GamePolicy` (`$game->user_id === $user->id`), apply `->can(...)` or `->scopeBindings()` on every `{game}` route.

### 1.3 Unauthenticated, unthrottled paid-LLM endpoints
`routes/web.php:40-51` — Chaos Mode is mounted **outside all auth middleware** ("No auth required — this is a public experiment"). Each of `start`/`turn`/`continue` fires a paid Claude call with no throttle, no CAPTCHA, no auth. Plus `GET /expansion-status` (`routes/web.php:53-127`) is unauthenticated and dumps **creator email addresses** + absolute server paths.

**Exploit:** anyone on the internet loops `POST /chaos-mode/turn` → unbounded Anthropic spend (financial DoS).
**Fix:** the product audit notes Chaos Mode is self-documented as removable and superseded — **cut it**. Delete or manager-gate `/expansion-status`.

### 1.4 Turn processing has no lock and no transaction — concurrent submits corrupt state
`PromptController.php:33-140` (found independently by both the scalability and logic-bug audits). **Verified:** zero `DB::transaction`, `lockForUpdate`, or `Cache::lock` in `app/`.

Each turn is a 30–55s read-modify-write: read `world_state`/`alignment_scaffold` → long AI call → full-overwrite `$game->update(...)` → `prompts()->create(...)`. Two rapid POSTs (double-click, impatient retry — which your own error UX *encourages*) → request B reads state before A commits; last write wins and **silently discards** the other turn's state delta, alignment tally, `defining_choice_*` triple, and symbolic memory. Both create prompt rows → duplicate narration in the UI. Same TOCTOU in `begin()` (`:101-166`) and `nextSession()` (`:211-365`).

The only current guard is a client-side `isSubmitting` flag — worthless against two tabs, a refresh, or a scripted client.
**Fix:** `DB::transaction` + `Game::lockForUpdate()` around the whole turn commit (this one fix closes logic bugs #1, #5, #6, #7). Needs a shared lock store → Redis (already provisioned, currently unused).

### 1.5 Missing indexes on the three hottest tables (Postgres)
DB is Postgres, which does **not** auto-index foreign keys. None of these have indexes:
- `prompts.game_id`, `prompts.session_number` — yet queried on **every turn** (`buildConversationHistory`, `PromptController.php:182-185`), on a table that grows one row per turn per player forever.
- `games.user_id`, `games.story_id` — hit on every game store.
- `events.chapter_id`, `events.session_number` — hit in the per-turn events/chapters join.

At a few thousand players this is a sequential scan per turn on a fast-growing table.
**Fix:** add composite indexes `prompts(game_id, session_number, created_at)`, `games(user_id, story_id)` (unique), `events(chapter_id)`, `events(session_number)`.

---

## 2. Scalability — the architecture, not the tuning

The 55s-timeout fix treated the symptom. The disease: **every narration turn synchronously holds one PHP-FPM worker + one Postgres connection for 15–55s**, non-streaming, on `begin`, `nextSession`, and every `store`.

| FPM workers | Sustainable concurrent players (~25s AI + ~45s think) | Postgres conns held |
|---|---|---|
| 25 | ~70 | 25 |
| 50 | ~140 | 50 |
| 100 | ~280 | 100 → **default `max_connections` exhausted** |
| for 1000 players | ~350–500 workers | 350–500 conns → needs PgBouncer (not configured) |

Beyond the ceiling, requests queue in the FPM backlog → the 504 cascade returns at load. No Octane, no Horizon, no queued turns, no streaming.

**Other scaling blockers:**
- **Local-disk storage** (`FILESYSTEM_DISK=private`): TTS MP3s + spatie media written locally → a second app server has cache-miss storms and duplicate files. **Hard horizontal-scaling blocker.** S3 is configured but not default.
- **TTS generated synchronously in a GET** with a 120s timeout (> Cloudflare's 100s) and no lock → long narrations 504 on audio, and N concurrent requests for the same clip = N parallel ElevenLabs charges (`TextToSpeechController.php:25-52`).
- **Queue `retry_after=90s` < job timeouts up to 900s+** (`config/queue.php:45`) → long adaptation jobs get re-delivered to a second worker mid-run → **duplicate LLM calls + racing writes**. Live bug.
- **One Postgres serves everything**: queue broker + cache store + session store + OLTP. Redis is provisioned and completely unused.
- **Zero application caching** (`grep Cache::` → nothing). Every turn re-fetches story + adaptation + *all* session-adaptation rows including 10–50KB JSON columns never needed at play time. Anthropic prompt caching (your own doc's "biggest win", ~90% input-cost cut) is **not implemented**.
- **Full narration history re-shipped in Inertia props every turn** (`GameController::show:56-73`) — a 100-turn game re-sends hundreds of KB per turn. No partial reloads, no streaming, no websockets (`BROADCAST_CONNECTION=log`).

**Order of attack:** (1) move narration to queued jobs + SSE/polling on Redis — the only change that makes "thousands" possible; (2) the indexes in §1.5; (3) per-game lock + transaction; (4) Anthropic prompt caching + the cheap wins already specced in your latency doc (items 4–7); (5) TTS → queued job + lock + S3/CDN; (6) cache/session → Redis, fix `retry_after`.

Your own `RnD Ideas/gameplay-latency-optimizations.md` is accurate but scoped only to *latency* — it never addresses concurrency architecture (worker holding, locking, indexes, queue driver), which are the real blockers.

---

## 3. Runtime logic bugs (beyond the concurrency root cause in §1.4)

- **HIGH — `max_tokens 2500` truncation soft-brick** (`ChaosEngineService.php:654-661` + schema `:87,94,143`): the schema *requires* the model to re-emit the complete `conditions`/`items`/`unresolved_promises` lists every turn; these grow monotonically over an 8-session game. When output exceeds 2500 tokens → truncated JSON → Prism throws → "please retry" — but the payload is the same size on retry, so **the game is deterministically bricked at that turn** with no fallback (server-side retry was removed by design; nothing shrinks the payload).
- **HIGH — no transaction around `$game->update()` + `prompts()->create()`** (`PromptController.php:125-140`): if the create fails, the world advanced but the narration row never exists — player never sees the turn, history is missing its narrator turn, yet state already holds its consequences.
- **HIGH — stale `next_session_opening` injected into wrong transition** (`GameController.php:258-274`): if no branch triple was committed during a session (a normal path), a prior session's authored hook opens the wrong later session.
- **MEDIUM — turns accepted after `session_complete`** (`PromptController::store`, no guard): player types instead of clicking "Next Session" → the next turn flips `current_session_complete` back to `false`, the button vanishes, and the armed authored close is already consumed → session can only end via improvisation.
- **MEDIUM — empty AI response persisted silently** (`ChaosEngineService.php:664`): `$response->structured ?? []` → blank narration + no choices saved, and `is_climactic_choice` overwritten to false, consuming an armed session close.
- **MEDIUM — silent `extractSessionNumber` fallback to session 1** (`:286-293`) defeats branch-label validation; a malformed `choice_id` overwrites a valid branch triple and poisons consequence lookups for the rest of the game.
- **MEDIUM — `reset()` never clears `completed_at`** (`GameController.php:392-418`): `GameReset.had_prior_completion` is wrong forever after; final-completion branch is re-runnable and overwrites completion timestamps → corrupts the avg-completion-time analytics the migration advertises.
- **MEDIUM — player action duplicated in model context** (`PromptController.php:33` vs `:76,103`): current action appears twice (in history *and* as PLAYER'S ACTION), wasting context and double-weighting.
- **LOW cluster:** unknown `games.model` slug → every turn 400s; `latest()` tiebreak on same-second rows; duplicate-game race (no unique index); `SkipToEnd` cycle-number omission.

**Verified non-bugs (good news):** `mergeStateDelta` correctly preserves state on missing/partial deltas; `choice_consequence_map` / `session_close_design` key shapes match between producer agents and runtime consumers; the Blade injection-token comment is inside `{{-- --}}` and never mis-anchors on the current template.

---

## 4. Security — remaining items

- **HIGH — hardcoded Google API key committed to git**: `database/stories/avatars/generate.sh:3` (`AIzaSy...`). Tracked by git. **Rotate the key now**, purge from history, move to env.
- **HIGH — zero rate limiting on any paid endpoint** (`grep throttle` → nothing): prompt, begin, next-session, transcribe (Whisper), TTS, voice-lab, chaos-mode. One user can script thousands of paid calls.
- **MEDIUM — unsanitized `v-html` on AI narration** (`GameplayChatCard.vue:181`, `ChaosMode.vue:633`, others): LLM output (whose context includes the player's free text) rendered raw → prompt-injection self-XSS. Sanitize with DOMPurify or an allowlist.
- **LOW — overbroad `$guarded`** on Game/User/Prompt (leaves `user_id`, `is_preview` mass-assignable — not currently reachable, but fragile); Writer Lab has no per-writer scoping (confirm if intended shared-editorial); Filament `canAccessPanel()` returns `true` (safe only because there's no manager/creator signup route — becomes open if one is added).

**Things that are correct:** user/writer registration + login are properly `guest`/`auth` gated; `.env` is git-ignored; no hardcoded secrets in `app/`/`config/`; session cookie is `http_only` + `same_site=lax` (ensure `SESSION_SECURE_COOKIE=true` in prod); no raw SQL; `SkipToEndCommand` is console-only, not HTTP-reachable.

---

## 5. Product map — what exists today

*(Stack note: frontend is **Vue 3** + PrimeVue/Tailwind, not React. Runtime narrator defaults to `claude-sonnet-4-6`.)*

**User journey:** Home → Stories browse (read-only comments, bookmark toggle, native URL share) → register (email+password, then a page that **echoes the plaintext password back from session** → forced username gate) → play one Game per story (gated to 12 hardcoded `LAUNCH_SLUGS`) → turn loop → completion → reset (which **deletes all prompts**).

| Subsystem | Maturity |
|---|---|
| Core Chaos play loop (choices, world state, alignment, branch consequences, authored closes) | **SOLID — the moat** |
| Adaptation pipeline (PDF→playable session architecture) | **SOLID** (internal CLI) |
| Analytics (PageView, UserActivityDay, completions, D1/D7/D30 retention widgets) | **SOLID — but measurement only, never acted on** |
| WriterLab (drafts, approve→activate, AI suggest/analyse, version restore) | **SOLID** internal tool |
| TTS + voice input | **SOLID** (known `eleven_v3` hallucination bug documented) |
| Bookmarks | **STUBBED** — toggle exists, **no "my bookmarks" page** |
| Comments | **STUBBED** — full moderation backend, **no way for users to post one** |
| Games library / dashboard | **STUBBED** — index + dashboard both redirect to homepage |
| "Join the Community" email capture | **FAKE** — pure markup, no backend, claims "4,000+ adventurers" |
| Chaos Mode (public no-auth) | **EXPERIMENTAL — cut it** |
| VoiceLab | **LAB** — authed but not in nav |

**Business gaps:**
- **Monetization: entirely absent.** No Stripe/Cashier/subscription/credits anywhere. Every turn is a full uncached Sonnet call + ElevenLabs v3 TTS, unlimited and free per account. **Unit economics are strictly negative with no counterweight.**
- **Retention: measured, never acted on.** `UserActivityDay` powers the retention widgets, but there is *zero* re-engagement machinery — no user emails, no streaks, no notifications, no "your story is waiting" nudge. `Analytics::abandonedCutoff()` already defines the trigger; nothing consumes it.
- **Virality: near zero.** Only the story URL is shareable — never the player's unique *run*. `defining_path_label` was added to `games` **today** and is unused. No OG images, no recap cards, no likes, no user ratings.
- **Funnel leak:** the homepage hero story (Alice) **isn't in `LAUNCH_SLUGS`** — the featured story isn't playable in the production runtime.

**Table-stakes missing:** account deletion/profile edit, ToS/privacy pages, user email verification (column exists, unused), accessibility (zero `aria-*` in the play screen), i18n, per-story OG.

---

## 6. Recommended features & pivots — the creative brief

Ranked by leverage against *what's already built*. The theme: **you built a machine that produces divergent, personal stories, then throws the output away.** Almost every high-leverage move is about capturing and compounding that output.

### Tier 0 — survival (do these first, they're not optional)
1. **Fix the two account-integrity holes** (§1.1, §1.2) and stop echoing plaintext passwords. Days of work, existential risk.
2. **Prompt caching + streaming.** Your own doc: ~90% input-cost cut, 40–60% latency cut. This *is* the monetization runway — you cannot price a product whose COGS you haven't controlled. Streaming also fixes the single biggest "feel" gap vs character.ai (the tab currently just hangs for 30–55s).

### Tier 1 — turn the engine's output into the product
3. **The Ending Archive / "Chronicle."** Stop deleting prompts on reset. Every completed run becomes a saved, revisitable artifact: the path taken, alignment, defining choices, the ending. This single change converts a disposable session into a *collection* — the foundation for identity, sharing, and replay. The data (`defining_path_label`, `GameCompletion`, alignment) already exists.
4. **Shareable Run-Recap Card.** Auto-generate a beautiful OG image + link from a finished run: *"I played Jekyll & Hyde — I walked the Ruthless path and chose to drink the serum. 41% of players didn't."* This is your growth loop, built from data you already persist. Every completed story becomes an ad.
5. **Choice social proof.** *"41% of players spared him."* You persist `defining_choice_id` per game — this is a one-query aggregate that makes every big choice feel weighty and communal. Cheap, and it's the single most character-defining mechanic AI Dungeon lacks.

### Tier 2 — retention & identity
6. **One re-engagement email: the abandoned-game nudge.** `Analytics::abandonedCutoff()` already defines the trigger. "Elena is still waiting in the fog outside Dr. Jekyll's laboratory…" — cliffhanger-driven, not "come back." Highest-ROI retention primitive you can ship.
7. **Cross-story reader identity / progression.** Titles, a "reader level," a shelf of completed chronicles, achievements for path diversity ("completed a story on every alignment"). Nothing carries between stories today — each is an island. This is what turns 12 stories into one platform.
8. **Finish ONE of {Bookmarks page, Games library, Comments}** into a single "My Library" surface (in-progress + bookmarked + completed chronicles). You have three half-built features that together make one essential screen.

### Tier 3 — the fun frontier
9. **Reactive story artifacts (in-scene image generation).** You have three RnD docs designing this and none built. A single generated image at each session's climactic beat would be transformative for shareability and immersion — and it's the visual layer character.ai and AI Dungeon both lean on. Gate it behind the paid tier (Tier 4) so it also earns its COGS.
10. **Parallel "what-if" branches.** One playthrough per story per user today. Let players fork at a past choice ("what if I hadn't?") — this is a *core* AI Dungeon behavior and it directly multiplies engagement per story you've paid to adapt.

### Tier 4 — the business model
11. **Freemium with a turn/story budget.** Free: N turns/day or the first session of any story. Paid ("Storyteller"): unlimited turns, voice narration, in-scene images, the full chronicle archive, early access to new adaptations. Your COGS map cleanly to tiers — voice and images are the expensive features and the obvious premium hooks. **Ship this only after prompt caching (#2)**, or you're subsidizing losses at scale.

### Cut list (reduce risk & surface)
Chaos Mode (open cost faucet), `/expansion-status`, OldHomepage/DesignRnd/BgRnd public routes, and either finish or hide the fake CommunitySignup and un-postable Comments.

---

## Appendix — the two-line summary for a stakeholder

> The interactive-fiction engine is excellent and genuinely differentiated. But the app currently lets anyone reset anyone's password and drive paid AI on anyone's game, loses game state on a double-click, has no way to make money, and structurally caps out around ~150 concurrent players. Roughly 2–3 focused weeks of security + concurrency + architecture work makes it *safe*; prompt caching + a chronicle/share loop + a paid tier makes it a *business*.
