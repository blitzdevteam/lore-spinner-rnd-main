# Lore Spinner Analytics

**Last updated:** 2026-06-06 (rev 3)  
**Author:** Implementation via Cursor  
**Status:** Production-ready — pending database migration (requires Docker / Sail)

**Analytics baseline:** `2026-06-01` (config: `analytics.start_date`, helper: `App\Support\Analytics::baseline()`). All queries exclude data before this date. Legacy activity is not tracked.

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture Decisions](#architecture-decisions)
3. [Database Schema](#database-schema)
4. [Metric Definitions & Source of Truth](#metric-definitions--source-of-truth)
5. [Instrumentation Points](#instrumentation-points)
6. [Dashboard](#dashboard)
7. [Story Analytics View](#story-analytics-view)
8. [Query Reference](#query-reference)
9. [Retention Logic](#retention-logic)
10. [Data Integrity Rules](#data-integrity-rules)
11. [Known Limitations & Future Work](#known-limitations--future-work)
12. [File Map](#file-map)
13. [How to Run Migrations](#how-to-run-migrations)
14. [Analytics Tests](#analytics-tests)

---

## Overview

Lore Spinner analytics tracks the player journey from first website visit through account creation, story gameplay, session progression, story completion, and replay. All analytics are stored in the PostgreSQL database alongside the application data — no external analytics service required.

The dashboard lives at `/manager/analytics` in the Filament admin panel.

### Design Principles

- **Prefer existing data over new tracking.** Every metric that could be derived from existing tables (`users`, `games`, `prompts`) was derived from those tables first. New tables were added only where no reliable source existed.
- **Immutable event tables for analytics history.** Mutable game state is wiped on reset. Analytics history must survive resets. This was the primary reason for separating `game_resets` from `games`.
- **Idempotent writes everywhere.** All instrumentation calls use upsert semantics. No metric can be double-counted by a retry or re-render.
- **PostgreSQL-native SQL.** The app uses PostgreSQL via Laravel Sail. All raw SQL uses PostgreSQL syntax (`::date`, `INTERVAL '1 day'`, boolean `false`).
- **No tracking of pre-existing data.** Columns added to `games` (`completed_at`) have no backfill. Metrics are forward-looking from the date migrations run. Historical counts will undercount until data accumulates.
- **Single baseline anchor.** All dashboard queries use `App\Support\Analytics::baseline()` (backed by `config/analytics.php`). Never hardcode `2026-06-01` in widgets or pages.

### Central baseline configuration

```php
// config/analytics.php
'start_date' => '2026-06-01',
'abandoned_inactivity_days' => 14,

// Usage in all analytics code:
use App\Support\Analytics;

Analytics::baseline();              // Carbon instance, start of day
Analytics::baselineDateTimeString(); // for raw SQL bindings
Analytics::abandonedCutoff();       // now() - inactivity days
```

---

## Architecture Decisions

### Why not a single `platform_events` table?

Considered and rejected. A generic events table (`event_type`, `user_id`, `metadata JSON`) is flexible but:

- Makes every query a `WHERE event_type = ...` scan with JSON extraction
- Provides no schema enforcement per event type
- Makes indexes difficult to design correctly
- Conflates fundamentally different event shapes into one table

Instead, each event type has its own table with a typed schema. This is more code but far more queryable.

### Why `game_resets` is immutable and append-only

The `games` table is mutable game state. When a player resets, all prompts are deleted and game columns are reset. If the replay counter lived on `games`, a second reset would overwrite the first reset's data.

`game_resets` is written once per reset and never modified. This makes it a reliable audit trail. Every time a player resets, a new row appears. `had_prior_completion` is calculated at insert time from `games.completed_at`, capturing the completion state before the reset clears anything.

### Why `game_session_completions` is now story-cycle-aware (not overwrite)

The table now uses `UNIQUE(game_id, story_cycle_number, session_number)` instead of the previous `UNIQUE(game_id, session_number)`.

Each story cycle (story cycle 1, after first reset = 2, etc.) gets its own set of session rows. A player who completes a story and replays twice produces **three full sets** of session records — one per story cycle — with all timing data preserved.

This change was made because overwriting session data on reset would prevent:
- Per-session duration analysis across story cycles ("does Session 3 get faster on replay?")
- Author analytics showing actual progression data, not just latest-run data
- Any longitudinal session timing analysis

`games.current_story_cycle_number` is incremented on every reset and drives which story cycle new session rows belong to.

### Why `game_completions` is the analytics source of truth for completions

`games.completed_at` is mutable current state — it records the most recent completion but is overwritten on each replay. `game_completions` is an append-only table (one row per completed story cycle) that preserves the complete completion history.

Analytics queries for "Story Completions" always use `game_completions.completed_at`, not `games.completed_at`. This distinction matters once players replay stories.

### Why `games.completed_at` is never cleared on reset

`games.completed_at` is the timestamp of the most recent story completion. It is **not** cleared when a player resets. This serves two purposes:

1. `game_resets.had_prior_completion` reads `games.completed_at` at insert time to determine whether this reset is a replay. If `completed_at` were cleared first, the check would always return `false`.
2. Historical completion queries (`WHERE completed_at BETWEEN ...`) still return correct results even if a player later replays.

If a player completes again after replaying, `completed_at` is updated to the new completion timestamp. The history of prior completions is preserved in `game_resets`.

### Why `user_activity_days` rather than `last_active_at`

`last_active_at` on `users` would only tell you when a user was last active, not the full history of their active days. Retention calculations require knowing:

- The user's first active day (cohort anchor)
- Whether they were active on specific days relative to that anchor

A `last_active_at` column cannot answer "was this user active on day 7 after their first game?" — `user_activity_days` can, with a simple `EXISTS` subquery.

Volume is low: at most one row per user per day. A user playing every day for a year generates 365 rows. 10,000 daily active users for a year = 3.65M rows — trivially manageable.

### Why `page_views` uses session-level dedup

Page views are deduplicated at the `(session_id, view_date)` level, not the `(user_id, view_date)` level. This means:

- Guest visitors (not logged in) are counted as unique sessions
- A single user opening a new private window gets a new session and counts as a second visit
- A logged-in user switching between stories in one session counts as one visit

This matches the standard industry definition of "unique visitors per day" and requires no cookies or user-level identification for guests.

---

## Database Schema

### `games` (modified)

Two analytics columns added:

```sql
completed_at              TIMESTAMP NULL DEFAULT NULL
current_story_cycle_number SMALLINT NOT NULL DEFAULT 1
```

**`completed_at`:**  
Set by `GameController@nextSession()` when `nextSessionNumber > totalSessions`.  
Cleared by: Never. Preserved across resets.  
Updated by: Each subsequent story completion overwrites the previous timestamp.  
Used for: `had_prior_completion` check in `game_resets`; NOT used as the analytics source of truth for completion counts.

**`current_story_cycle_number`:**  
Starts at 1. Incremented on every player-initiated reset in `GameController@reset()`.  
Drives the unique key on `game_session_completions` — session rows are scoped to a story cycle.  
Used for: `GameSessionCompletion::updateOrCreate` and `GameCompletion::updateOrCreate` keys.

| Column | Type | Notes |
|--------|------|-------|
| `id` | ULID (char 26) | Primary key |
| `story_id` | bigint FK → stories | |
| `is_preview` | boolean | Writer Lab preview games — excluded from all analytics |
| `user_id` | bigint FK → users | |
| `current_session_number` | unsigned int nullable | 1-based, resets to 1 on reset |
| `current_story_cycle_number` | smallint | **ANALYTICS: story cycle 1 on first start, increments on every reset** |
| `model` | varchar 64 | AI model slug |
| `world_state` | jsonb | |
| `symbolic_memory` | text | |
| `alignment_scaffold` | jsonb | |
| `defining_choice_id` | varchar 128 | |
| `defining_choice_line` | text | |
| `is_climactic_choice` | boolean | |
| `current_session_complete` | boolean | LLM session boundary signal |
| `completed_at` | timestamp nullable | **ANALYTICS: latest completion timestamp (mutable)** |
| `created_at` | timestamp | **ANALYTICS: story start timestamp** |
| `updated_at` | timestamp | |

---

### `game_completions` (new)

One row per completed story cycle. Append-only analytics source of truth for story completions.

```sql
CREATE TABLE game_completions (
    id                  BIGSERIAL PRIMARY KEY,
    game_id             CHAR(26)    NOT NULL REFERENCES games(id) ON DELETE CASCADE,
    user_id             BIGINT      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    story_id            BIGINT      NOT NULL REFERENCES stories(id) ON DELETE CASCADE,
    story_cycle_number  SMALLINT    NOT NULL DEFAULT 1,
    completed_at        TIMESTAMP   NOT NULL,
    created_at          TIMESTAMP   NOT NULL,
    updated_at          TIMESTAMP   NOT NULL,
    UNIQUE (game_id, story_cycle_number)
);

CREATE INDEX ON game_completions (story_id, completed_at);
CREATE INDEX ON game_completions (user_id, completed_at);
```

**Written by:** `GameController@nextSession()` — in the same block that sets `games.completed_at`.  
**Upserted** on `(game_id, story_cycle_number)` for idempotency.  
**Analytics query:** `WHERE completed_at >= '2026-06-01'`  
**Relationship to `games.completed_at`:** They are written simultaneously but serve different purposes. `games.completed_at` is mutable current state; `game_completions` is immutable history.

---

### `game_session_completions` (new — updated schema)

One row per `(game_id, story_cycle_number, session_number)`. Preserves history across all story cycles.

```sql
CREATE TABLE game_session_completions (
    id                  BIGSERIAL PRIMARY KEY,
    game_id             CHAR(26)    NOT NULL REFERENCES games(id) ON DELETE CASCADE,
    story_id            BIGINT      NOT NULL REFERENCES stories(id) ON DELETE CASCADE,
    user_id             BIGINT      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    story_cycle_number  SMALLINT    NOT NULL DEFAULT 1,
    session_number      SMALLINT    NOT NULL CHECK (session_number > 0),
    started_at          TIMESTAMP   NULL,
    completed_at        TIMESTAMP   NULL,
    created_at          TIMESTAMP   NOT NULL,
    updated_at          TIMESTAMP   NOT NULL,
    UNIQUE (game_id, story_cycle_number, session_number)
);

CREATE INDEX ON game_session_completions (story_id, story_cycle_number, session_number, completed_at);
CREATE INDEX ON game_session_completions (user_id, completed_at);
```

**`story_cycle_number`:** Matches `games.current_story_cycle_number` at the time the row is written. Story cycle 1 on first start, cycle 2 after first reset, etc. This is the key that separates per-story-cycle data.

**`started_at`:** Set when narration for this session is first delivered.  
- Session 1: set in `GameController@begin()` after first `prompts` row created.  
- Session N > 1: set in `GameController@nextSession()` after AI succeeds for session N.

**`completed_at`:** Set when the player successfully advances past this session.  
- Set in `GameController@nextSession()` on the *current* session (N), right before creating session N+1.  
- On the final session: set in `GameController@nextSession()` in the `nextSessionNumber > totalSessions` branch.  
- **Not set if the AI call fails** — instrumentation is inside the try block.

**On replay (after reset):** A NEW row is created for the new `story_cycle_number`. Prior story cycle rows are **never touched**. Session history is fully preserved across all story cycles.

---

### `user_activity_days` (new)

One row per `(user_id, activity_date)`. Idempotent.

```sql
CREATE TABLE user_activity_days (
    id            BIGSERIAL PRIMARY KEY,
    user_id       BIGINT   NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    activity_date DATE     NOT NULL,
    created_at    TIMESTAMP NOT NULL,
    updated_at    TIMESTAMP NOT NULL,
    UNIQUE (user_id, activity_date)
);

CREATE INDEX ON user_activity_days (activity_date);
```

**Written by:** `UserActivityDay::record(int $userId)` — a static method that calls `DB::table()->upsert()`.  
**Called from:**
- `GameController@begin()` — after first narration created
- `GameController@nextSession()` — on session advance or story completion
- `GameController@reset()` — on player reset
- `PromptController@store()` — on every player turn

**Not called for:** Account creation, login, profile update, browsing — only game interactions.

---

### `page_views` (new)

One row per `(session_id, view_date)`. Idempotent.

```sql
CREATE TABLE page_views (
    id         BIGSERIAL PRIMARY KEY,
    user_id    BIGINT       NULL REFERENCES users(id) ON DELETE SET NULL,
    session_id VARCHAR(40)  NOT NULL,
    path       VARCHAR(512) NOT NULL,
    view_date  DATE         NOT NULL,
    created_at TIMESTAMP    NOT NULL,
    updated_at TIMESTAMP    NOT NULL,
    UNIQUE (session_id, view_date),
    INDEX (view_date),
    INDEX (session_id)
);
```

**Written by:** `RecordPageView` middleware.  
**Registered on routes:** `/`, `/stories/*` (index + show), `/creators/*` (index + show).  
**Trigger condition:** `GET` request + successful response + **no `X-Inertia` header** (excludes Inertia partial navigations).

The `X-Inertia` header check means only the initial full-page load per browser tab creates a record. Subsequent client-side navigations within the same session do not create additional rows. This correctly counts unique browser sessions per day.

**`user_id`:** Nullable — guest visitors are tracked by session only.  
**`path`:** First path visited in this session on this date (not updated on subsequent navigations due to upsert).

---

### `game_resets` (new)

Immutable append-only event log. One row per player-initiated reset.

```sql
CREATE TABLE game_resets (
    id                    BIGSERIAL PRIMARY KEY,
    game_id               CHAR(26) NOT NULL REFERENCES games(id) ON DELETE CASCADE,
    user_id               BIGINT   NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    story_id              BIGINT   NOT NULL REFERENCES stories(id) ON DELETE CASCADE,
    had_prior_completion  BOOLEAN  NOT NULL DEFAULT FALSE,
    created_at            TIMESTAMP NOT NULL,
    updated_at            TIMESTAMP NOT NULL,
    INDEX (story_id, created_at),
    INDEX (user_id, had_prior_completion)
);
```

**Written by:** `GameController@reset()` — the very first operation before any state is modified.  
**`had_prior_completion`:** `games.completed_at IS NOT NULL` at the moment of reset. `true` = this reset is a replay.  
**Never modified after insert.**

---

## Metric Definitions & Source of Truth

### Visits

**Business definition:** A unique visitor opening the website or application.  
**Technical definition:** One distinct `session_id` per calendar day in `page_views`.  
**Source table:** `page_views`  
**Query column:** `view_date` for date filtering, `session_id` for uniqueness.  
**Note:** Guest sessions (logged-out) are counted. Inertia soft-navigations within a session are not counted separately.

```sql
SELECT COUNT(DISTINCT session_id)
FROM page_views
WHERE view_date BETWEEN :from AND :to;
```

**Data available from:** Migration date forward.  
**Historical data:** None — no backfill possible for visits before this migration.

---

### Account Creations

**Business definition:** A user successfully completing registration.  
**Technical definition:** A row created in `users` by `RegisterController@store()`.  
**Source table:** `users`  
**Query column:** `created_at`  
**No new tracking required** — this data existed before this implementation.

```sql
SELECT COUNT(*)
FROM users
WHERE created_at BETWEEN :from AND :to;
```

**Data available from:** Platform launch. Full historical data exists.  
**Note:** Counts registrations, not email verifications. Email verification status is tracked separately as `email_verified_at`.

---

### Story Starts

**Business definition:** A user creating a new gameplay instance for a story — `Game::create()`.  
**Technical definition:** A row created in `games` (excluding `is_preview = true` rows).  
**Source table:** `games`  
**Query column:** `created_at`  
**No new tracking required.**

> **Definition is stable and intentional.** Story Start = `Game::create()`. This is NOT the same as  
> `game_session_completions.started_at` (when narration is first delivered). A game record is created  
> the moment a player confirms they want to play. Even if they leave before the first AI response arrives,  
> they started a story. The dashboard always uses `games.created_at WHERE is_preview = false`.

```sql
SELECT COUNT(*)
FROM games
WHERE is_preview = false
  AND created_at BETWEEN :from AND :to;
```

**Important constraint:** Only one `games` row exists per user per story. `GameController@store()` returns an existing game rather than creating a new one. This means:
- `games.created_at` = the first and only time this user initiated this story
- Resets do NOT create a new row — they mutate the existing one
- The count of unique `(user_id, story_id)` pairs = the count of story starts

**Data available from:** Platform launch. Full historical data exists.  
**Excludes:** `is_preview = true` games created by the Writer Lab adaptation pipeline.

---

### First Session Completions

**Business definition:** A player successfully completing Session 1 of a story.  
**Technical definition:** A row in `game_session_completions` where `session_number = 1` and `completed_at IS NOT NULL`.  
**Source table:** `game_session_completions`  
**Query column:** `completed_at`

```sql
SELECT COUNT(*)
FROM game_session_completions
WHERE session_number = 1
  AND completed_at IS NOT NULL
  AND completed_at BETWEEN :from AND :to;
```

**When `completed_at` is set:** When `GameController@nextSession()` successfully advances a game from session 1 to session 2. This requires:
1. The LLM to have signaled `session_complete = true` (sets `games.current_session_complete`)
2. The player to have clicked "Next Session"
3. The AI engine to successfully generate the session 2 opener
4. All database writes to succeed

**On replay:** A new session 1 row is created for the incremented `story_cycle_number`. Prior cycle rows are preserved unchanged.

**Data available from:** Migration date forward.

---

### Story Completions

**Business definition:** A player reaching the actual end state of a story (any story cycle).  
**Technical definition:** A row in `game_completions` with `completed_at >= '2026-06-01'`. One row per completed story cycle.  
**Source table:** `game_completions` (NOT `games.completed_at`)  
**Query column:** `completed_at`

```sql
SELECT COUNT(*)
FROM game_completions
WHERE completed_at >= '2026-06-01'
  AND completed_at <= :to;
```

**When written:** `GameController@nextSession()` — in the branch where `nextSessionNumber > totalSessions`. The same call also sets `games.completed_at = now()`.

**Why `game_completions` and not `games.completed_at`:**
`games.completed_at` is overwritten on each replay. A player who completes a story three times leaves one `games.completed_at` row (the third completion) but three `game_completions` rows (one per story cycle).

**Two completion metrics — both intentional:**
- **Completion Events** (`COUNT(*)` on `game_completions`) — total engagement; one player completing 3 cycles = 3 events.
- **Unique Games Completed** (`COUNT(DISTINCT game_id)`) — funnel completion rate; one player completing 3 cycles = 1 unique game. **Completion % uses this denominator** so rates never exceed 100%.

**`games.completed_at` is still set** for backward compatibility with game state logic (e.g., `had_prior_completion` check in `game_resets`). It is the current/mutable state. `game_completions` is the append-only analytics record.

**Upserted on** `(game_id, story_cycle_number)` for idempotency against retries.

**Not recorded if:** The player abandons on the last session without clicking "Next Session." Player-confirmed completion only.

**Data available from:** Migration date forward.

---

### Incomplete Stories

**Business definition:** A player who started a story and has not yet completed it. They may still be actively playing.  
**Technical definition:** A `games` row where `is_preview = false`, `created_at >= baseline`, and `completed_at IS NULL`.  
**Source table:** `games`  
**Important:** This is current state, not a permanent label. A player counted as "incomplete" today may complete tomorrow.

```sql
SELECT COUNT(*)
FROM games
WHERE is_preview = false
  AND created_at >= '2026-06-01'
  AND completed_at IS NULL;
```

**Dashboard card:** Shows raw count and as % of starts.  
**Do not confuse with Abandoned.** Incomplete includes both active players and truly inactive ones.

---

### Abandoned Stories

**Business definition:** A player who started a story, has not completed it, and has had no gameplay activity for 14 or more days.  
**Technical definition:** Incomplete game with `MAX(last gameplay event) < NOW() - 14 days`.  

**Gameplay activity sources** (no page views — gameplay only):
- `games.created_at`
- `prompts.created_at`
- `game_session_completions.started_at`
- `game_session_completions.completed_at`
- `game_resets.created_at`

```sql
WITH last_activity AS (
    SELECT g.id, g.story_id,
        GREATEST(
            g.created_at,
            MAX(p.created_at),
            MAX(gsc.started_at),
            MAX(gsc.completed_at),
            MAX(gr.created_at)
        ) AS last_active_at
    FROM games g
    LEFT JOIN prompts p ON p.game_id = g.id
    LEFT JOIN game_session_completions gsc ON gsc.game_id = g.id
    LEFT JOIN game_resets gr ON gr.game_id = g.id
    WHERE g.is_preview = false
      AND g.completed_at IS NULL
      AND g.created_at >= '2026-06-01'
    GROUP BY g.id, g.created_at, g.story_id
)
SELECT COUNT(*)
FROM last_activity
WHERE last_active_at < NOW() - INTERVAL '14 days';
```

**No new column needed.** The `abandoned_at` timestamp is not stored — abandonment is always derived on query. This avoids stale data and keeps the schema minimal.

**14-day threshold:** Chosen as the inactivity window. Revisit when you have enough data to determine the right cutoff for your specific engagement patterns.

**Data available from:** Migration date forward for activity tracking.

---

### Returns

**Business definition:** A player becoming active again on a different calendar day after previous activity.  
**Technical definition:** A distinct `user_id` in `user_activity_days` with at least one `activity_date` within the selected date range AND at least one `activity_date` before the start of the selected date range.  
**Source table:** `user_activity_days`  
**Date filter dependency:** Returns requires a `from` date to define "prior to this period." Returns is always 0 when filter is "All time."

```sql
SELECT COUNT(DISTINCT a.user_id)
FROM user_activity_days a
WHERE a.activity_date >= :from
  AND a.activity_date <= :to
  AND EXISTS (
      SELECT 1
      FROM user_activity_days b
      WHERE b.user_id = a.user_id
        AND b.activity_date < :from
  );
```

**Interpretation:** "How many players who were previously active came back during this period?"  
**Not the same as D1/D7/D30 retention**, which is cohort-based. Returns is a simple reactivation count.

**Data available from:** Migration date forward. Requires at least two separate calendar days of activity data to produce non-zero results.

---

### Replays

**Business definition:** A player starting a new story cycle of a story they have already completed in the past.  
**Technical definition:** A row in `game_resets` where `had_prior_completion = true`.  
**Source table:** `game_resets`  
**Query column:** `created_at`

```sql
SELECT COUNT(*)
FROM game_resets
WHERE had_prior_completion = true
  AND created_at BETWEEN :from AND :to;
```

**When recorded:** `GameController@reset()` — before any state is cleared. `had_prior_completion` is set to `games.completed_at IS NOT NULL` at the moment of reset.

**Counts events, not unique users.** A player who completes a story and resets twice = 2 replay events.

> **Critical query guard:** Dashboard replay queries MUST always include `WHERE had_prior_completion = true`.  
> Every reset is recorded — including resets of abandoned story cycles (`had_prior_completion = false`).  
> If you omit the filter, you count both replays AND abandoned-then-reset plays, inflating the number.  
>  
> The only valid replay is the pattern:  
> `Completed → Reset → had_prior_completion = true`  
>  
> NOT:  
> `Started → Abandoned → Reset → had_prior_completion = false`

**Data available from:** Migration date forward.  
**Resets with `had_prior_completion = false`:** Also recorded, available for future abandonment analysis, but never surfaced as "Replays" in any dashboard widget.

---

### D1 Retention

**Business definition:** Percentage of players who return the day after their first story start.  
**Cohort:** All users with at least one non-preview `games` row, ever.  
**Cohort anchor:** `MIN(games.created_at)::date` per user (the date of their very first game).  
**Return signal:** A row in `user_activity_days` with `activity_date = first_start + INTERVAL '1 day'`.

```sql
-- Part of the combined retention query
LEFT JOIN user_activity_days d1
    ON  d1.user_id       = c.user_id
    AND d1.activity_date = c.first_start + INTERVAL '1 day'
```

**Result:** `d1_retained / cohort_size * 100`  
**Note:** D1 counts returning on exactly day 1, not "within 1 day."

---

### D7 Retention

**Business definition:** Percentage of players who return within 7 days of their first story start.  
**Return signal:** Any row in `user_activity_days` with `activity_date BETWEEN first_start + 1 day AND first_start + 7 days`.  
**Note:** Counts users with ANY activity in the 7-day window, not just on day 7.

---

### D30 Retention

**Business definition:** Percentage of players who return within 30 days of their first story start.  
**Return signal:** Any row in `user_activity_days` with `activity_date BETWEEN first_start + 1 day AND first_start + 30 days`.

---

### Return Rate

**Business definition:** Percentage of players who return after their initial gameplay session.  
**Technical definition:** Players with more than one distinct `activity_date` in `user_activity_days`, divided by all players with any non-preview game.

```sql
SELECT
    COUNT(DISTINCT g.user_id) AS total_players,
    COUNT(DISTINCT CASE WHEN a.day_count > 1 THEN g.user_id END) AS returners
FROM games g
LEFT JOIN (
    SELECT user_id, COUNT(DISTINCT activity_date) AS day_count
    FROM user_activity_days
    GROUP BY user_id
) a ON a.user_id = g.user_id
WHERE g.is_preview = false;
```

**Result:** `returners / total_players * 100`  
**Note:** Not filtered by date. This is an all-time metric — "of everyone who has ever played, what fraction returned on a different day?"

---

## Instrumentation Points

### `GameController@begin()`
**File:** `app/Http/Controllers/User/GameController.php`  
**Trigger:** Player clicks "Start Story" → cinematic fires → POST `/user/games/{game}/begin`  
**Guard:** Skipped if `$game->prompts()->exists()` (idempotent retry protection)

Inside try block, after `$game->prompts()->create()`:

```php
GameSessionCompletion::updateOrCreate(
    [
        'game_id'            => $game->id,
        'story_cycle_number' => $game->current_story_cycle_number,
        'session_number'     => 1,
    ],
    [
        'story_id'     => $story->id,
        'user_id'      => $game->user_id,
        'started_at'   => now(),
        'completed_at' => null,
    ],
);

UserActivityDay::record($game->user_id);
```

**Effect:**
- Creates session 1 row for the **current story cycle** — prior cycle rows are untouched
- Records today as an active day for the player

---

### `GameController@nextSession()` — story complete branch
**File:** `app/Http/Controllers/User/GameController.php`  
**Trigger:** Player clicks "Next Session" on the final session  
**Condition:** `$nextSessionNumber > $totalSessions`

```php
$now = now();

GameSessionCompletion::where('game_id', $game->id)
    ->where('story_cycle_number', $game->current_story_cycle_number)
    ->where('session_number', $currentSessionNumber)
    ->update(['completed_at' => $now]);

$game->update(['completed_at' => $now]);

GameCompletion::updateOrCreate(
    [
        'game_id'            => $game->id,
        'story_cycle_number' => $game->current_story_cycle_number,
    ],
    [
        'user_id'      => $game->user_id,
        'story_id'     => $game->story_id,
        'completed_at' => $now,
    ],
);

UserActivityDay::record($game->user_id);
```

**Effect:**
- Marks the final session as completed for the current story cycle
- Sets `games.completed_at` (current/mutable state)
- Appends immutable row to `game_completions` (analytics source of truth)
- Records today as an active day

---

### `GameController@nextSession()` — session advance branch
**File:** `app/Http/Controllers/User/GameController.php`  
**Trigger:** Player clicks "Next Session" on a non-final session  
**Condition:** `$nextSessionNumber <= $totalSessions`, inside try block after AI succeeds

```php
GameSessionCompletion::where('game_id', $game->id)
    ->where('story_cycle_number', $game->current_story_cycle_number)
    ->where('session_number', $currentSessionNumber)
    ->update(['completed_at' => now()]);

GameSessionCompletion::updateOrCreate(
    [
        'game_id'            => $game->id,
        'story_cycle_number' => $game->current_story_cycle_number,
        'session_number'     => $nextSessionNumber,
    ],
    [
        'story_id'     => $story->id,
        'user_id'      => $game->user_id,
        'started_at'   => now(),
        'completed_at' => null,
    ],
);

UserActivityDay::record($game->user_id);
```

**Effect:**
- Closes session N with a `completed_at` timestamp
- Opens session N+1 with a `started_at` timestamp
- Records today as an active day

**Not called if the AI engine call fails.** Session completion is only recorded after the AI successfully generates the next session opener.

---

### `GameController@reset()`
**File:** `app/Http/Controllers/User/GameController.php`  
**Trigger:** Player clicks "Reset" / "Start Over"

```php
GameReset::create([
    'game_id'              => $game->id,
    'user_id'              => $game->user_id,
    'story_id'             => $game->story_id,
    'had_prior_completion' => $game->completed_at !== null,
]);

$game->update([
    'current_session_number'     => 1,
    'current_story_cycle_number' => $game->current_story_cycle_number + 1,
    // ... other state cleared ...
]);

UserActivityDay::record($game->user_id);
```

**Effect:**
- Appends a `game_resets` row — analytics history preserved even as game state is erased
- `had_prior_completion` captures completion state before any clearing
- Increments `current_story_cycle_number` — next `begin()` writes to a new story cycle
- Records today as an active day
- `games.completed_at` is **NOT cleared** (preserved for replay detection)
- Prior `game_session_completions` rows are **NOT deleted or overwritten** — each story cycle has its own rows

---

### `PromptController@store()`
**File:** `app/Http/Controllers/User/Game/PromptController.php`  
**Trigger:** Player submits a turn (any choice or Continue)

Inside try block, after `$game->prompts()->create()`:

```php
UserActivityDay::record($user->id);
```

**Effect:**
- Records today as an active day
- This is the most frequent instrumentation call — fires on every player turn

---

### `RecordPageView` middleware
**File:** `app/Http/Middleware/RecordPageView.php`  
**Routes:** `GET /`, `GET /stories`, `GET /stories/{story}`, `GET /creators`, `GET /creators/{creator}`  
**Condition:** `$request->isMethod('GET') && $response->isSuccessful() && !$request->header('X-Inertia')`

```php
DB::table('page_views')->upsert(
    [
        'user_id'    => $request->user('user')?->id,
        'session_id' => $request->session()->getId(),
        'path'       => mb_substr($request->path(), 0, 512),
        'view_date'  => now()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ],
    uniqueBy: ['session_id', 'view_date'],
    update: ['updated_at' => now()],
);
```

**Effect:**
- Creates or silently updates a `page_views` row for this session + today
- The unique constraint ensures only one row per session per day — makes "distinct sessions" = "unique visitors"

---

## Dashboard

**URL:** `/manager/analytics`  
**Auth:** Manager guard (`auth:manager`)  
**File:** `app/Filament/Manager/Pages/AnalyticsDashboard.php`  
**View:** `resources/views/filament/manager/pages/analytics-dashboard.blade.php`

### Widget 1: Platform Metrics (`AnalyticsKpiWidget`)

**Type:** `StatsOverviewWidget` (Filament KPI cards)  
**File:** `app/Filament/Manager/Widgets/Analytics/AnalyticsKpiWidget.php`  
**Date filters:** None — all-time data capped at the configured baseline and current as-of time

Nine KPI cards:

| Card | Color | Metric |
|------|-------|--------|
| Visits | Gray | Unique sessions on public pages |
| Signups | Purple | New accounts created |
| Story Starts | Blue | New games created (excl. previews) |
| Session 1 Completions | Yellow | Players who advanced past Session 1 |
| Story Completions | Green | Distinct games that reached the story end state |
| Incomplete Stories | Yellow | Non-preview games with no completion on record |
| Abandoned Stories | Red | Incomplete games with no gameplay activity for the configured inactivity window |
| Returning Users | Purple | Users active in the period with prior activity before it |
| Replay Events | Blue | Reset events after a prior story completion |

---

### Widget 2: Conversion Funnel (`AnalyticsFunnelWidget`)

**Type:** `ChartWidget` — horizontal bar chart (`indexAxis: 'y'`)  
**File:** `app/Filament/Manager/Widgets/Analytics/AnalyticsFunnelWidget.php`  
**Date filters:** Same as KPI widget

Shows four nested gameplay steps (Story Starts, Session 1 Done, Story Complete, Replayed Games) as a horizontal bar chart. Visits and Signups remain separate because those populations cannot be proven to nest inside gameplay starts.

Each bar has a distinct color matching the KPI card color scheme.

---

### Widget 3: Retention (`AnalyticsRetentionWidget`)

**Type:** `StatsOverviewWidget` (Filament KPI cards)  
**File:** `app/Filament/Manager/Widgets/Analytics/AnalyticsRetentionWidget.php`  
**Date filter:** None — all-time cohort

Four cards:

| Card | Color | Metric |
|------|-------|--------|
| D1 Retention | Purple | % of mature baseline-cohort users who returned the next day |
| D7 Retention | Blue | % of mature baseline-cohort users who returned within 7 days |
| D30 Retention | Yellow | % of mature baseline-cohort users who returned within 30 days |
| Return Rate | Green | % of baseline-cohort users with >1 distinct active day |

Each D-N card shows its mature cohort size in the description: `"Cohort: 432 users · returned within 7 days"`.

If the cohort is empty (no `user_activity_days` data yet), all rates display as `N/A`.

---

## Story Analytics View

**Route:** `/manager/story-analytics`  
**File:** `app/Filament/Manager/Pages/StoryAnalyticsPage.php`  
**View:** `resources/views/filament/manager/pages/story-analytics.blade.php`

A dedicated Filament page showing per-story engagement metrics in a sortable table. Only stories with at least one gameplay start appear. Metrics are computed live from the production tables.

### Columns (summary table)

| Column | Source | Formula |
|--------|--------|---------|
| **Story** | `stories.title`, `stories.slug` | Display only |
| **Starts** | `games` | `COUNT(*) WHERE is_preview = false AND created_at BETWEEN baseline AND as-of` |
| **Completed (unique)** | `game_completions` + `games` | `COUNT(DISTINCT game_id)` where the non-preview game and completion event are both in the baseline/as-of window |
| **Completion %** | Derived | `unique completed / starts × 100` (never exceeds 100%) |
| **Completion Events** | `game_completions` | `COUNT(*) WHERE completed_at BETWEEN baseline and as-of` (all story cycles; can exceed starts) |
| **Incomplete** | `games` | `COUNT(*) WHERE completed_at IS NULL AND created_at BETWEEN baseline and as-of` |
| **Incomplete %** | Derived | `incomplete / starts × 100` |
| **Abandoned** | Derived | Incomplete games with no gameplay activity for 14+ days |
| **Abandoned %** | Derived | `abandoned / starts × 100` |
| **Replay Events** | `game_resets` | `COUNT(*) WHERE had_prior_completion = true AND created_at BETWEEN baseline and as-of` |
| **Unique Replayers** | `game_resets` + `games` | `COUNT(DISTINCT user_id)` on cohort games with a qualifying reset in the baseline/as-of window |
| **Replay %** | Derived | `unique replayers / unique completed × 100` (not events ÷ events) |
| **Avg Session** | `game_session_completions` | `AVG(completed_at − started_at)` in minutes |
| **Avg Completion** | `game_completions` + session 1 | `AVG(gc.completed_at − s1.started_at)` matched by `(game_id, story_cycle_number)` |
| **Drop-off Session** | `game_session_completions` | Session # with the largest `reached − completed` gap |

### Content Progression (per story)

Distinct games reaching each step — the primary content diagnostic:

```text
Started → Reached S2 → Reached S3 → Reached S4 → Completed
```

| Step | Query |
|------|-------|
| **Started** | `games` created after baseline |
| **Reached S{N}** | `COUNT(DISTINCT game_id)` in `game_session_completions` where `session_number = N` and `started_at IS NOT NULL` |
| **Completed** | `game_completions` count for story |

Example: `100 starts → 92 reach S2 → 81 reach S3 → 24 reach S4 → 22 complete` immediately shows Session 3 is the problem.

### Session Funnel (per story, per session)

| Column | Formula |
|--------|---------|
| **Reached** | Session rows with `started_at IS NOT NULL` |
| **Completed** | Session rows with `completed_at IS NOT NULL` |
| **Dropped** | Reached − Completed |
| **Completion %** | Completed / Reached |
| **Avg / Median Duration** | Mean and `PERCENTILE_CONT(0.5)` of `completed_at − started_at` in minutes |

Median duration is shown alongside average because averages are skewed by outliers (e.g. one 3-hour session inflating a 10-minute typical experience).

### Color Coding

**Completion %:** ≥ 60% green · 30–59% yellow · < 30% red  
**Abandoned %:** < 30% green · 30–59% yellow · ≥ 60% red  
**Drop-off Session:** Red badge `Session N`

### Drop-off Session Query

```sql
SELECT DISTINCT ON (story_id)
    story_id,
    session_number
FROM (
    SELECT
        story_id,
        session_number,
        COUNT(*) FILTER (WHERE started_at IS NOT NULL)                   AS reached,
        COUNT(*) FILTER (WHERE completed_at IS NOT NULL)                 AS completed_count,
        COUNT(*) FILTER (WHERE started_at IS NOT NULL)
          - COUNT(*) FILTER (WHERE completed_at IS NOT NULL)             AS dropped
    FROM game_session_completions
    WHERE started_at IS NOT NULL
    GROUP BY story_id, session_number
) sub
WHERE dropped > 0
ORDER BY story_id, dropped DESC;
```

`DISTINCT ON (story_id) ... ORDER BY story_id, dropped DESC` selects the session with the most absolute drop for each story.

### Summary Footer

Below the table a one-line summary shows: total stories, total starts, total completions, overall completion rate, and total replays (post-completion only).

### Why this page, not a widget

The retention and KPI widgets are global (platform-wide) and belong on the main dashboard. Per-story analytics are story-scoped and naturally belong on a separate page. Authors eventually want per-story dashboards in the creator panel — this page establishes the data model for that future view.

---

## Query Reference

### Cohort Definition for Retention

```sql
SELECT gsc.user_id, MIN(gsc.started_at)::date AS first_play
FROM game_session_completions gsc
JOIN games g ON g.id = gsc.game_id
WHERE g.is_preview = false
  AND gsc.started_at IS NOT NULL
  AND gsc.started_at >= '2026-06-01'
GROUP BY gsc.user_id
```

Cohort anchor: **first playable experience** — the date narration was first delivered (`game_session_completions.started_at`), not game row creation. Players who clicked Play but never received AI content are excluded from the cohort.

The cohort query considers only playable sessions at or before the dashboard as-of time and keeps users whose first observed playable session is on or after the configured baseline. D1, D7, and D30 each use a separate mature denominator: users are included only when the corresponding milestone date has arrived.

### Full Retention Query (single pass)

The live widget runs this logic in one query with a `cohort` CTE and three maturity checks:

```text
cohort = users whose first observed playable session is on/after baseline
D1     = cohort first_play <= as_of - 1 day, activity exactly first_play + 1 day
D7     = cohort first_play <= as_of - 7 days, activity from day 1 through day 7
D30    = cohort first_play <= as_of - 30 days, activity from day 1 through day 30
```

Return Rate uses the same cohort and counts users with more than one distinct activity day from first play through the as-of date.

### Session Drop-off per Story (not in dashboard — future query)

```sql
SELECT
    s.title,
    gsc.session_number,
    COUNT(DISTINCT gsc.game_id) AS reached_count,
    COUNT(DISTINCT CASE WHEN gsc.completed_at IS NOT NULL THEN gsc.game_id END) AS completed_count,
    ROUND(
        COUNT(DISTINCT CASE WHEN gsc.completed_at IS NOT NULL THEN gsc.game_id END)::numeric
        / NULLIF(COUNT(DISTINCT gsc.game_id), 0) * 100,
        1
    ) AS session_completion_rate
FROM game_session_completions gsc
JOIN stories s ON s.id = gsc.story_id
GROUP BY s.id, s.title, gsc.session_number
ORDER BY s.title, gsc.session_number;
```

### Avg Session Duration per Story (not in dashboard — future query)

```sql
SELECT
    s.title,
    gsc.session_number,
    ROUND(AVG(EXTRACT(EPOCH FROM (gsc.completed_at - gsc.started_at)) / 60), 1) AS avg_minutes,
    COUNT(*) AS sample_size
FROM game_session_completions gsc
JOIN stories s ON s.id = gsc.story_id
WHERE gsc.started_at IS NOT NULL
  AND gsc.completed_at IS NOT NULL
GROUP BY s.id, s.title, gsc.session_number
ORDER BY s.title, gsc.session_number;
```

### Replay Count by Story (not in dashboard — future query)

```sql
SELECT
    s.title,
    COUNT(*) AS total_resets,
    SUM(CASE WHEN gr.had_prior_completion THEN 1 ELSE 0 END) AS replays,
    SUM(CASE WHEN NOT gr.had_prior_completion THEN 1 ELSE 0 END) AS abandoned_resets
FROM game_resets gr
JOIN stories s ON s.id = gr.story_id
GROUP BY s.id, s.title
ORDER BY replays DESC;
```

---

## Retention Logic

### Cohort Anchor

The cohort anchor is `MIN(games.created_at)::date` per user — the date of a player's very first game, regardless of which story. This is the most stable anchor because:

- It cannot be retroactively changed
- It represents the moment the player entered the product
- It survives story resets (which don't delete the game row, only mutate it)

### D1 Definition

D1 retention counts users with an `activity_date` of exactly `first_start + 1 day`. This is strict — a user who returned 2 days later counts for D7 but not D1.

### D7 / D30 Definition

D7 and D30 use `BETWEEN` ranges: any activity day within the window counts. A user who returned on day 2, day 5, and day 7 counts as D7 retained regardless.

### Return Rate vs. D-N Retention

These are different metrics:

| Metric | Cohort scope | Time window | What it measures |
|--------|-------------|-------------|-----------------|
| D1 Retention | All-time players | Exactly 1 day after first start | Early stickiness |
| D7 Retention | All-time players | Any day 1–7 after first start | First-week engagement |
| D30 Retention | All-time players | Any day 1–30 after first start | Long-term habit |
| Return Rate | All-time players | All time | Ever came back at all |
| Returns (KPI) | Selected period | Active in period with prior history | Reactivation in period |

### Why D-N Retention Is All-Time (Not Period-Filtered)

Filtering the D-N cohort by date range would give misleadingly small numbers because:

- D30 retention requires at least 30 days of data per cohort member
- Filtering to "last 30 days" would exclude any players from that cohort who haven't yet passed the 30-day mark
- A player who started 10 days ago is correctly excluded from D30 retention — they haven't had the chance to return at day 30 yet

The all-time cohort is accurate. The dashboard description makes this explicit: `"All-time player cohort"`.

---

## Data Integrity Rules

1. **`games.is_preview = true` rows must be excluded from all analytics.** These are Writer Lab pipeline test games. Queries always add `WHERE is_preview = false` (Eloquent) or `WHERE g.is_preview = false` (raw SQL).

2. **`game_resets` must be written before any game state is cleared.** The `reset()` controller reads `$game->completed_at` to determine `had_prior_completion`. If clearing happened first, this would always be `null`.

3. **`completed_at` on `games` is never cleared.** If cleared, prior-completion detection in `game_resets` would break, and historical completion queries would undercount.

4. **Session completion instrumentation is inside the try block.** A failed AI call does not advance the session and must not record a session completion. Analytics writes that happen inside try blocks are only reached if the AI succeeds.

5. **`UserActivityDay::record()` is safe to call multiple times per day.** The upsert uses `UNIQUE (user_id, activity_date)` to prevent duplicate rows.

6. **`page_views` upsert uses `(session_id, view_date)` — not `(user_id, view_date)`.** This correctly handles guest users who have no `user_id`. If deduplication were by `user_id`, guest sessions would conflict or be lost.

---

## Known Limitations & Future Work

### Current Limitations

**Visits undercount vs. traditional analytics:**  
Only initial full-page loads are recorded (no `X-Inertia` header). A user who opens the app for the first time counts as 1 visit. A user who navigates directly to `/stories/wizard-of-oz` from Google counts as 1 visit. Session continuations within the same browser tab do not add rows. This is by design — it counts sessions, not pageviews.

**Story Completions do not backfill:**  
Games completed before the migration have `completed_at = null`. The manager dashboard's "all time" completion count will undercount legacy completions. This gap closes over time as players complete stories post-migration.

**Returns uses the baseline as its period start:**
The Returns query counts users active from the analytics baseline through the as-of date who also had activity before the baseline. It is intentionally not a lifetime count of every user with more than one active day.

**D-N Retention will show 0 until data accumulates:**  
`user_activity_days` is empty on migration day. D1, D7, and D30 will all show `N/A` until players create activity days. D30 will take at least 30 days of data after at least one player's first start to show a real number.

**Session history is fully preserved across story cycles:**  
The `UNIQUE(game_id, story_cycle_number, session_number)` key means each story cycle produces its own complete set of session rows. A player who completes a story and replays three times has four full sets of session records (one per story cycle). No data is overwritten. The "session history overwrite" issue described in earlier drafts no longer applies.

**Chaos Mode sessions not tracked:**  
Chaos Mode (`/chaos-mode`) uses `ChaosSession` (separate model, no `user_id` on sessions). Its analytics are entirely separate and not included in this implementation.

### Future Work

**Session drop-off funnel per story:** ✅ Implemented — the Story Analytics page renders a full session-by-session funnel table per story showing Reached, Completed, Dropped, Completion %, Avg Duration, and a mini progress bar. The highest-drop session is highlighted as `↓ drop-off`.

**Avg session duration per session:** ✅ Implemented in the session funnel table. Each session row shows its own avg duration, revealing "Session 3 takes 47 min on average vs. the estimated 20 min."

**Story-level analytics page:** ✅ Implemented — `/manager/story-analytics` shows summary table (Starts, Completions, Incomplete, Abandoned, Replays, Avg Session, Avg Completion, Drop-off), Content Progression funnel, and per-session funnel with avg/median duration.

**Session 1–N completion %:** ✅ Shown in the session funnel section — effectively the per-session completion rates: S1: 88%, S2: 74%, S3: 31%, S4: 95%.

**Cohort-based retention chart:**  
Weekly cohorts (all players who started in week X) plotted as retention curves. Requires the `user_activity_days` table to have enough data — roughly 30+ days of operation.

**UTM / traffic source tracking:**  
Add `utm_source`, `utm_medium`, `utm_campaign` to `page_views` to understand which marketing channels convert to signups and starts.

**Chaos Mode analytics:**  
Separate instrumentation for `ChaosSession` — chaos starts, turns per session, session completions — would give signal on the Alice in Wonderland experiment's engagement.

**Author-facing analytics:**  
Future writer panel showing per-story completion rates, session drop-off, and engagement depth. The data is already being collected; the UI just needs to be built for the creator or writer guard.

---

## File Map

### Migrations

| File | What it does |
|------|-------------|
| `database/migrations/2026_06_06_000001_add_completed_at_to_games_table.php` | Adds `completed_at` to `games` |
| `database/migrations/2026_06_06_000002_create_game_session_completions_table.php` | Creates `game_session_completions` (original) |
| `database/migrations/2026_06_06_000003_create_user_activity_days_table.php` | Creates `user_activity_days` |
| `database/migrations/2026_06_06_000004_create_page_views_table.php` | Creates `page_views` |
| `database/migrations/2026_06_06_000005_create_game_resets_table.php` | Creates `game_resets` |
| `database/migrations/2026_06_06_000006_add_story_cycle_number_to_games_table.php` | Adds `current_story_cycle_number` to `games` |
| `database/migrations/2026_06_06_000007_add_story_cycle_number_to_game_session_completions_table.php` | Adds `story_cycle_number`, new unique key to `game_session_completions` |
| `database/migrations/2026_06_06_000008_create_game_completions_table.php` | Creates `game_completions` (append-only completion history) |

### Models

| File | What it represents |
|------|--------------------|
| `app/Models/GameCompletion.php` | **NEW** — Immutable story completion record (one per story cycle) |
| `app/Models/GameSessionCompletion.php` | Per-session lifecycle record (updated: +`story_cycle_number`) |
| `app/Models/UserActivityDay.php` | Daily activity record (includes static `::record()`) |
| `app/Models/PageView.php` | Public page visit record |
| `app/Models/GameReset.php` | Immutable reset event log |

### Modified models

| File | What changed |
|------|-------------|
| `app/Models/Game.php` | Added `completed_at` property + cast; added `sessionCompletions()` and `resets()` HasMany relations |

### New middleware

| File | What it does |
|------|-------------|
| `app/Http/Middleware/RecordPageView.php` | Writes `page_views` on public route full-page loads |

### Modified controllers

| File | What changed |
|------|-------------|
| `app/Http/Controllers/User/GameController.php` | Added analytics writes to `begin()`, `nextSession()`, `reset()` |
| `app/Http/Controllers/User/Game/PromptController.php` | Added `UserActivityDay::record()` call after each turn |

### Modified routes

| File | What changed |
|------|-------------|
| `routes/web.php` | Wrapped `/`, `/stories/*`, `/creators/*` with `RecordPageView` middleware |

### New Filament pages & widgets

| File | What it renders |
|------|----------------|
| `app/Filament/Manager/Pages/AnalyticsDashboard.php` | Platform dashboard at `/manager/analytics` |
| `resources/views/filament/manager/pages/analytics-dashboard.blade.php` | Blade view for platform dashboard |
| `app/Filament/Manager/Widgets/Analytics/AnalyticsKpiWidget.php` | 9 KPI stat cards (incl. Incomplete + Abandoned) |
| `config/analytics.php` | Central `start_date` and `abandoned_inactivity_days` config |
| `app/Support/Analytics.php` | `Analytics::baseline()` — single source for all dashboard date anchors |
| `app/Filament/Manager/Widgets/Analytics/AnalyticsFunnelWidget.php` | Horizontal bar funnel chart |
| `app/Filament/Manager/Widgets/Analytics/AnalyticsRetentionWidget.php` | D1/D7/D30/Return Rate cards |
| `app/Filament/Manager/Pages/StoryAnalyticsPage.php` | Per-story table at `/manager/story-analytics` |
| `resources/views/filament/manager/pages/story-analytics.blade.php` | Blade view for story analytics |

---

## How to Run Migrations

Docker must be running (the app uses Laravel Sail with PostgreSQL):

```bash
# Start the environment
./vendor/bin/sail up -d

# Run all pending migrations
./vendor/bin/sail artisan migrate

# Verify new tables exist
./vendor/bin/sail artisan tinker --execute="echo implode(', ', DB::connection()->getDoctrineSchemaManager()->listTableNames());"
```

To roll back these migrations specifically:

```bash
./vendor/bin/sail artisan migrate:rollback --step=5
```

After migration, the tables are empty. Data accumulates from the next user action. There is no backfill.

---

## Analytics Tests

Automated Pest tests validate instrumentation semantics, metric definitions, and dashboard queries.

**Runbook:** [`analytics/TEST-RUNBOOK.md`](TEST-RUNBOOK.md)

**Quick run:**

```bash
./vendor/bin/sail artisan test tests/Feature/Analytics
```

**Result log (persists after fixture cleanup):**

```bash
cat storage/logs/analytics-tests.log
```

Tests use `AnalyticsTestContext` for self-cleaning fixtures and `AnalyticsTestLogger` for metric snapshots.
