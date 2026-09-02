# Laravel Cloud — V2.2 Adaptation Commands (Verified)

Practical command reference from the **Wizard of Oz** V2.2 re-adaptation on Laravel Cloud (June 2026).  
Replace `the-wonderful-wizard-of-oz` with your story slug where needed.

**Boiler pacing** commands (adaptation export bundle, **game evidence bundle §6.1**, **recent plays §6.2**, **estimated playtime §6.3**, **injected runtime prompt §6**, probes, **`stories:rebuild-runtime-prompts`**, `--boiler` runs) are in **§6** — example slug: `the-adventure-of-the-speckled-band`.

Companion exports live in `Exported JSON/` (`Oz-V2-2.json`, `oz-voice-profile.json`).

---

## 0. Prerequisites

### Infrastructure (Laravel Cloud canvas)

| Resource | Config |
|---|---|
| **Worker cluster** | Custom worker, **Processes: 3** |
| **Worker command** | `php artisan queue:work database --queue=adaptation --tries=1 --timeout=900` |
| **Object storage** | Bucket attached to environment; disk name **`public`** (default: Yes) |
| **Queue connection** | Jobs live on **`database`** queue `adaptation` (not Redis unless env says otherwise) |

After attaching a bucket, **redeploy** so injected storage config is active.

Verify storage is wired:

```bash
php artisan tinker --execute='
echo "default: " . config("filesystems.default") . PHP_EOL;
$d = config("filesystems.disks.public");
echo "public driver: " . ($d["driver"] ?? "?") . PHP_EOL;
echo "public bucket: " . ($d["bucket"] ?? "NULL") . PHP_EOL;
'
```

Expect `public driver: s3` and a non-null bucket after redeploy.  
**Do not** use `Storage::disk("s3")` when Cloud names the bucket disk `public`.

### During a pipeline run

- **Do not deploy** — each deploy shuts down the worker cluster mid-run.
- Cloud CLI commands **timeout at 30 minutes** — use the **background worker** for long runs, not CLI drain alone.

---

## 1. Check adaptation status

```bash
php artisan tinker --execute='
$story = App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->firstOrFail();
$a = $story->adaptation;
echo "status: " . $a->adaptation_status->value . PHP_EOL;
echo "voice_profile: " . (!empty($a->voice_profile) ? "present" : "MISSING") . PHP_EOL;
echo "sessions: " . $a->sessionAdaptations->count() . PHP_EOL;
echo "pending jobs: " . DB::table("jobs")->where("queue","adaptation")->count() . PHP_EOL;
'
```

Sessions are on **`$story->adaptation->sessionAdaptations()`**, not `$story->sessionAdaptations()`.

### Full story pipeline status (seed + adaptation + failed-job check)

Replace `anima-machina` with any slug. Answers: is seed done? is adaptation done? did old `failed_jobs` rows get recovered on retry?

```bash
php artisan tinker --execute='
$slug = "anima-machina";
$story = App\Models\Story::where("slug", $slug)->first();

echo "=== PIPELINE STATUS: {$slug} ===\n\n";

if (! $story) {
    echo "STORY: NOT IN DB\n";
    echo "NEXT: SEED_STORY={$slug} php artisan db:seed --class=AddSingleStorySeeder --force\n";
    echo "pending adaptation: " . DB::table("jobs")->where("queue", "adaptation")->count() . "\n";
    return;
}

echo "STORY id={$story->id}\n";
echo "  status:        {$story->status->value}\n";
echo "  created_at:    {$story->created_at}\n";
echo "  updated_at:    {$story->updated_at}\n";
echo "  system_prompt: " . (filled($story->system_prompt) ? "yes" : "MISSING") . "\n";
echo "  opening:       " . (filled($story->opening) ? strlen($story->opening) . " chars" : "MISSING") . "\n";
echo "  script:        " . ($story->getFirstMedia("script")?->file_name ?? "MISSING") . "\n";

$chapters = $story->chapters()->orderBy("position")->get();
echo "\nCHAPTERS: {$chapters->count()} | events: {$story->events()->count()}\n";
foreach ($chapters as $ch) {
    echo "  Ch{$ch->position}: {$ch->status->value} | events={$ch->events()->count()}\n";
}

$a = $story->adaptation;
echo "\nADAPTATION: " . ($a ? $a->adaptation_status->value : "none") . "\n";
if ($a) {
    $sessions = $a->sessionAdaptations()->orderBy("session_number")->get();
    foreach ($sessions as $s) {
        $rp = $s->runtime_narrator_prompt;
        echo "  S{$s->session_number}: {$s->session_status->value}"
            . " | choices=" . (filled($s->session_choice_design) ? "y" : "n")
            . " | consequences=" . (filled($s->choice_consequence_map) ? "y" : "n")
            . " | runtime=" . (filled($rp) ? strlen($rp) . "ch" : "MISSING")
            . " | updated {$s->updated_at}\n";
    }
    $runtimeReady = $sessions->filter(fn ($s) => filled($s->runtime_narrator_prompt))->count();
    echo "  v2_ready: {$runtimeReady}/{$sessions->count()}\n";
}

echo "\nQUEUE\n";
foreach (["adaptation", "chapter-extraction", "image-generation", "default"] as $q) {
    $n = DB::table("jobs")->where("queue", $q)->count();
    if ($n > 0) echo "  {$q}: {$n} pending\n";
}

$fieldForJob = [
    "ChoiceDesignJob" => "session_choice_design",
    "ConsequenceMappingJob" => "choice_consequence_map",
    "SessionCloseJob" => "session_close_design",
    "EditorialVerificationJob" => "editorial_verification",
    "RuntimeNarratorAssemblyJob" => "runtime_narrator_prompt",
    "EntryPointDiagnosisJob" => "entry_point_diagnosis",
    "SessionArchitectureJob" => "session_architecture",
];

echo "\nFAILED JOBS (this story — recovered vs still broken)\n";
$failed = DB::table("failed_jobs")->orderByDesc("failed_at")->limit(25)->get();
$storyId = $story->id;
$shown = 0;
foreach ($failed as $f) {
    if (! str_contains($f->payload, "\"id\":{$storyId}") && ! str_contains($f->payload, $slug)) {
        continue;
    }
    $p = json_decode($f->payload, true);
    $name = $p["displayName"] ?? "?";
    $short = class_basename($name);
    $field = $fieldForJob[$short] ?? null;
    $verdict = "UNKNOWN — inspect exception";
    if ($a && $field && $sessions->isNotEmpty()) {
        $missing = $sessions->filter(fn ($s) => ! filled($s->{$field}))->count();
        $latest = $sessions->max("updated_at");
        if ($missing === 0) {
            $verdict = ($latest && $f->failed_at < $latest)
                ? "RECOVERED (all sessions have output; updated after failure)"
                : "OUTPUT PRESENT (failure may be stale)";
        } else {
            $verdict = "STILL BROKEN ({$missing} session(s) missing {$field})";
        }
    }
    echo "  [{$f->failed_at}] {$short}\n";
    echo "    {$verdict}\n";
    echo "    " . Illuminate\Support\Str::limit($f->exception, 120) . "\n";
    $shown++;
}
if ($shown === 0) echo "  (no failed_jobs rows matching this story in last 25)\n";

echo "\n--- NEXT STEP ---\n";
if ($story->status->value !== "published") {
    echo "Seed incomplete → wipe + SEED_STORY={$slug} php artisan db:seed --class=AddSingleStorySeeder --force\n";
} elseif (! $a) {
    echo "php artisan stories:run-adaptation {$slug} --force\n";
} elseif (DB::table("jobs")->where("queue", "adaptation")->count() > 0) {
    echo "Adaptation jobs still pending — wait for workers.\n";
} elseif ($a->sessionAdaptations->every(fn ($s) => filled($s->runtime_narrator_prompt))) {
    if ($a->adaptation_status->value !== "completed") {
        echo "Run reconciliation:\n";
        echo "  App\\Jobs\\Adaptation\\AdaptationStatusReconciliationJob::dispatchSync(\$story);\n";
    } else {
        echo "DONE — seed + adaptation complete. Playable if on LAUNCH_SLUGS.\n";
    }
} else {
    echo "php artisan stories:run-adaptation {$slug} --force\n";
}
'
```

**Reading failed-job verdicts**

| Verdict | Meaning |
|---|---|
| `RECOVERED` | Failure is old; all sessions now have that job’s output and rows were updated after the failure |
| `OUTPUT PRESENT` | Data exists; failure row is likely stale (retried successfully) |
| `STILL BROKEN` | Re-run adaptation for that phase |
| `UNKNOWN` | Story-wide job (Voice Lock, Session Map, etc.) — read the exception line |

After all runtime prompts exist but status is `partial-completion`:

```bash
php artisan tinker --execute='
$story = App\Models\Story::where("slug","anima-machina")->firstOrFail();
App\Jobs\Adaptation\AdaptationStatusReconciliationJob::dispatchSync($story);
echo $story->adaptation->fresh()->adaptation_status->value . PHP_EOL;
'
```

Expect: `completed`.

### Full session grid

```bash
php artisan tinker --execute='
$story = App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->firstOrFail();
$a = $story->adaptation;
$sessions = $a->sessionAdaptations()->orderBy("session_number")->get();
$ready = 0;
foreach ($sessions as $s) {
  $rp = $s->runtime_narrator_prompt ?? "";
  if (strlen($rp) > 0) $ready++;
  echo "S{$s->session_number}: {$s->session_status->value}"
    . " entry=" . (!empty($s->entry_point_diagnosis) ? "y" : "n")
    . " choices=" . (!empty($s->session_choice_design) ? "y" : "n")
    . " runtime=" . (strlen($rp) > 0 ? strlen($rp)."ch" : "MISSING")
    . PHP_EOL;
}
echo "v2_ready: {$ready}/{$sessions->count()}" . PHP_EOL;
'
```

### V2.2 voice profile gates (novelist)

```bash
php artisan tinker --execute='
$v = App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->firstOrFail()->adaptation->voice_profile;
$dna = $v["author_voice_dna_profile"] ?? [];
echo "profile_type: " . ($v["profile_type"] ?? "MISSING") . PHP_EOL;
echo "collocations: " . count($dna["collocation_fingerprint"] ?? []) . " (>=15)" . PHP_EOL;
echo "negative_space: " . count($dna["negative_space_map"] ?? []) . " (>=5)" . PHP_EOL;
echo "audit_points: " . count($v["fourteen_point_audit_protocol"] ?? []) . " (=14)" . PHP_EOL;
'
```

---

## 2. Run / re-run adaptation

Oz slug: **`the-wonderful-wizard-of-oz`**

```bash
php artisan stories:run-adaptation the-wonderful-wizard-of-oz --force
```

Dispatches to the `adaptation` queue only — does **not** run inline and does **not** dispatch image jobs.

---

## 3. Queue worker (the fix that made jobs move)

Jobs are on **`database`** connection, queue **`adaptation`**. A generic `queue:work` without `database` and `--queue=adaptation` will not drain them.

### Test one job (diagnostic)

```bash
php artisan queue:work database --queue=adaptation --once --tries=1 --timeout=900 -v
```

Expect: `App\Jobs\Adaptation\... RUNNING` then `DONE`.

### Drain all pending jobs (CLI — may hit 30-min limit)

```bash
php artisan queue:work database --queue=adaptation --tries=1 --timeout=900 --stop-when-empty -v
```

Keep the **background worker** running for Oz-sized runs (~1–2+ hours for session phase).

### Restart workers after config change

```bash
php artisan queue:restart
```

---

## 4. Resume mid-pipeline (without `--force`)

### After Voice Lock merge only

If `voice_profile` is present but sessions are missing/stale, dispatch session map:

```bash
php artisan tinker --execute='
$story = App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->firstOrFail();
App\Jobs\Adaptation\StorySessionMapJob::dispatch($story)->onQueue("adaptation");
echo "StorySessionMapJob dispatched";
'
```

### After all sessions have runtime prompts but status is `partial-completion`

```bash
php artisan tinker --execute='
$story = App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->firstOrFail();
App\Jobs\Adaptation\AdaptationStatusReconciliationJob::dispatchSync($story);
echo $story->adaptation->fresh()->adaptation_status->value;
'
```

Expect: `completed`.

---

## 5. Export artifacts

### Built-in export (session phases — no voice_profile or runtime prompts)

```bash
php artisan adaptation:export the-wonderful-wizard-of-oz
```

Writes to `database/exports/adaptation-{slug}-{timestamp}.csv` and `.json` on the server.

**List exports on server** (filename changes every run):

```bash
ls -la /var/www/html/database/exports/adaptation-the-wonderful-wizard-of-oz-*.json
```

If the directory is empty or the file is gone (deploy/ephemeral disk), re-export first:

```bash
php artisan adaptation:export the-wonderful-wizard-of-oz
```

Verify size of latest file:

```bash
wc -c /var/www/html/database/exports/adaptation-the-wonderful-wizard-of-oz-*.json
```

Read on Cloud CLI (replace `*` with actual timestamp from `ls`):

```bash
cat /var/www/html/database/exports/adaptation-the-wonderful-wizard-of-oz-*.json
```

---

## 6. Upload exports to Laravel Cloud bucket

Bucket disk name is **`public`** (not `s3`). Private bucket — download via **bucket File explorer**, not `/storage/...` URLs.

### Voice profile

```bash
php artisan tinker --execute='
$json = json_encode(App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->first()->adaptation->voice_profile, JSON_UNESCAPED_UNICODE);
$dest = "exports/oz-voice-profile.json";
echo "source: " . strlen($json) . " bytes\n";
Illuminate\Support\Facades\Storage::disk("public")->put($dest, $json);
echo "uploaded: " . Illuminate\Support\Facades\Storage::disk("public")->size($dest) . " bytes\n";
'
```

### Adaptation JSON (auto-finds latest server export)

Finds the newest `adaptation-the-wonderful-wizard-of-oz-*.json` under `database/exports/`.  
If none exists, run `php artisan adaptation:export the-wonderful-wizard-of-oz` first.

```bash
php artisan tinker --execute='
$slug = "the-wonderful-wizard-of-oz";
$dir = database_path("exports");
$files = glob($dir . "/adaptation-{$slug}-*.json");
if (empty($files)) {
  echo "No export found in {$dir}. Run: php artisan adaptation:export {$slug}\n";
  exit(1);
}
usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
$src = $files[0];
$dest = "exports/oz-adaptation.json";
$content = file_get_contents($src);
echo "source file: " . basename($src) . "\n";
echo "source: " . strlen($content) . " bytes\n";
if (strlen($content) < 1000) { echo "ABORT: source too small\n"; exit(1); }
Illuminate\Support\Facades\Storage::disk("public")->put($dest, $content);
echo "uploaded: " . Illuminate\Support\Facades\Storage::disk("public")->size($dest) . " bytes\n";
'
```

### Runtime prompt (one session — repeat for 2–6)

```bash
php artisan tinker --execute='
$n = 1;
$rp = App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->first()->adaptation->sessionAdaptations()->where("session_number",$n)->first()->runtime_narrator_prompt;
$dest = "exports/oz-session-{$n}-runtime.txt";
echo "source: " . strlen($rp) . " bytes\n";
Illuminate\Support\Facades\Storage::disk("public")->put($dest, $rp);
echo "uploaded: " . Illuminate\Support\Facades\Storage::disk("public")->size($dest) . " bytes\n";
'
```

### Runtime prompts — all sessions in one shot

```bash
php artisan tinker --execute='
$story = App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->first();
foreach (range(1, 6) as $n) {
  $rp = $story->adaptation->sessionAdaptations()->where("session_number",$n)->first()->runtime_narrator_prompt ?? null;
  if (!$rp) { echo "S{$n}: MISSING\n"; continue; }
  $dest = "exports/oz-session-{$n}-runtime.txt";
  Illuminate\Support\Facades\Storage::disk("public")->put($dest, $rp);
  echo "S{$n}: uploaded " . strlen($rp) . " bytes\n";
}
'
```

All 6 files land in the bucket under `exports/` in one run. Replace slug and session range for other stories.

### Runtime prompt — cached assembly vs fully injected

Two different exports — do not confuse them:

| Export | Source | Injection tokens | Use when |
|---|---|---|---|
| **Cached assembly** | `session_adaptations.runtime_narrator_prompt` | Still present (`[OPENING_SCENE_INJECTION_POINT]`, etc.) | Adaptation QA, diffing template rebuilds |
| **Fully injected** | `ChaosEngineService::renderSystemPrompt()` with `isSessionStart=true` | Filled in — cold open, tiers, active moment | What the model actually sees on session 1 turn 1 |

Change **`$slug`** and **`$session`**. Optional **`$gid`** when you want the live game's active moment card injected (Boiler stories).

#### Cached assembly only → bucket

```bash
php artisan tinker --execute='
$slug = "wasteland";
$session = 1;
$rp = App\Models\Story::where("slug", $slug)->firstOrFail()
  ->adaptation->sessionAdaptations()->where("session_number", $session)->value("runtime_narrator_prompt");
if (!filled($rp)) { echo "MISSING runtime_narrator_prompt for {$slug} S{$session}\n"; return; }
$dest = "exports/{$slug}-session-{$session}-runtime-cached.txt";
Illuminate\Support\Facades\Storage::disk("public")->put($dest, $rp);
echo "uploaded {$dest}: " . strlen($rp) . " bytes\n";
'
```

#### Fully injected (session start) → bucket — recommended

Renders the same pipeline as `ProcessGameOpeningAction` / `chaos:dump-prompt`: empty world state, session-start opening block, runtime injection points resolved.

```bash
php artisan tinker --execute='
$slug = "wasteland";
$session = 1;
$gid = null;
$engine = app(App\Services\ChaosEngineService::class);
$story = App\Models\Story::where("slug", $slug)->firstOrFail();
$game = $gid ? App\Models\Game::find($gid) : null;
$ctx = $engine->loadSessionContext($story, $session, null, null, null, $game);
if ($ctx === null) { echo "MISSING adaptation/runtime prompt for {$slug} S{$session}\n"; return; }
$rp = $engine->renderSystemPrompt($ctx, $engine->emptyWorldState(), $engine->emptyAlignmentScaffold(), null, true, false);
$suffix = $gid ? "-game-{$gid}" : "";
$dest = "exports/{$slug}-session-{$session}-runtime-injected{$suffix}.txt";
Illuminate\Support\Facades\Storage::disk("public")->put($dest, $rp);
echo "uploaded {$dest}: " . strlen($rp) . " bytes\n";
echo "active_moment=" . ($ctx["active_moment_context"] ? "yes" : "no") . "\n";
'
```

**With a live game** (includes `[ACTIVE_PLAYABLE_MOMENT_INJECTION_POINT]` from that save — e.g. Tom's Wasteland opening as-played):

```bash
php artisan tinker --execute='
$slug = "wasteland";
$session = 1;
$gid = "PASTE_GAME_ULID_HERE";
$engine = app(App\Services\ChaosEngineService::class);
$story = App\Models\Story::where("slug", $slug)->firstOrFail();
$game = App\Models\Game::findOrFail($gid);
$ctx = $engine->loadSessionContext($story, $session, null, null, null, $game);
$rp = $engine->renderSystemPrompt($ctx, $engine->emptyWorldState(), $engine->emptyAlignmentScaffold(), null, true, false);
$dest = "exports/{$slug}-session-{$session}-runtime-injected-game-{$gid}.txt";
Illuminate\Support\Facades\Storage::disk("public")->put($dest, $rp);
echo "uploaded {$dest}: " . strlen($rp) . " bytes\n";
'
```

#### Stdout only (quick check on Cloud CLI)

May truncate very long prompts — prefer bucket upload above for the full file.

```bash
php artisan chaos:dump-prompt wasteland --session=1
```

**Quick recipes:**

| Request | `$slug` | `$session` | `$gid` |
|---|---|---|---|
| Wasteland S1 injected (generic) | `"wasteland"` | `1` | `null` |
| PJ's S1 injected | `"pjs"` | `1` | `null` |
| Tom's Wasteland S1 with active moment | `"wasteland"` | `1` | `01kyk029yz3y3y39b05d60d53x` |

**Worked example — Thomas / Wasteland** (evidence: `docs/Boiler/Debug/Thomas log/Thomas latest test on-wasteland-01kyk029yz3y3y39b05d60d53x-evidence.json`):

| Field | Value |
|---|---|
| `game_id` | `01kyk029yz3y3y39b05d60d53x` |
| `user` | `thomas@lorespinner.com` |
| `story` | `wasteland` |
| `session` | 1 |
| `moment` | `S1_M04` (at export) |
| `turns` | 11 |

Use `$gid = null` above for a **fresh S1 opening** (no active moment card). Use Tom's `game_id` when you want the **current moment card** (`S1_M04` at time of export) injected into the prompt.

### Boiler — full output bundle → File explorer (recommended)

Uploads **whatever Boiler has produced so far** — draft, finalize manifest, normalized scene cards, event roles, runtime prompts, and one combined JSON. Partial runs are fine; only existing artifacts are written.

Replace `$slug` and `$prefix` for other stories (example: Speckled Band).

```bash
php artisan tinker --execute='
$slug = "the-adventure-of-the-speckled-band";
$prefix = "exports/speckled-band-boiler";
$story = App\Models\Story::where("slug", $slug)->firstOrFail();
$a = $story->adaptation->load("sessionAdaptations.playableCut.moments", "sessionAdaptations.playableCut.eventRoles");
$disk = Illuminate\Support\Facades\Storage::disk("public");

$bundle = [
  "story" => ["id" => $story->id, "title" => $story->title, "slug" => $story->slug],
  "exported_at" => now()->toIso8601String(),
  "adaptation_status" => $a->adaptation_status->value,
  "boiler_enabled" => (bool) $a->boiler_enabled,
  "story_wide" => [
    "boiling_profile" => data_get($a->story_session_map, "boiling_profile"),
    "format_detection" => $a->format_detection,
    "ip_audit" => $a->ip_audit,
    "voice_profile" => $a->voice_profile,
    "story_session_map" => $a->story_session_map,
  ],
  "sessions" => $a->sessionAdaptations->sortBy("session_number")->map(function ($s) {
    $cut = $s->playableCut;
    return [
      "session_number" => $s->session_number,
      "session_status" => $s->session_status->value,
      "boiler_status" => $s->boiler_status->value,
      "boiler_error" => $s->boiler_error,
      "entry_point_diagnosis" => $s->entry_point_diagnosis,
      "boiler_draft" => $s->boiler_draft,
      "session_architecture" => $s->session_architecture,
      "session_choice_design" => $s->session_choice_design,
      "choice_consequence_map" => $s->choice_consequence_map,
      "session_close_design" => $s->session_close_design,
      "boiler_final_cut" => $s->boiler_final_cut,
      "editorial_verification" => $s->editorial_verification,
      "runtime_narrator_prompt_chars" => strlen((string) $s->runtime_narrator_prompt),
      "normalized_cut" => $cut ? [
        "cut_key" => $cut->cut_key,
        "status" => $cut->status,
        "moments" => $cut->moments->sortBy("moment_order")->map(fn ($m) => [
          "moment_key" => $m->moment_key,
          "moment_order" => $m->moment_order,
          "anchor_event_id" => $m->anchor_event_id,
          "source_event_ids" => $m->source_event_ids,
          "scene_card" => $m->scene_card,
        ])->values(),
        "event_roles" => $cut->eventRoles->map(fn ($r) => [
          "event_id" => $r->event_id,
          "primary_role" => $r->primary_role,
          "included_moment_key" => $r->included_moment_key,
          "why" => $r->why,
          "reintroduction_note" => $r->reintroduction_note,
        ])->values(),
      ] : null,
    ];
  })->values(),
];

$json = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$fullPath = "{$prefix}/full-boiler-output.json";
$disk->put($fullPath, $json);
echo "uploaded {$fullPath}: " . $disk->size($fullPath) . " bytes\n";

foreach ($a->sessionAdaptations->sortBy("session_number") as $s) {
  $n = $s->session_number;

  if (!empty($s->boiler_draft)) {
    $path = "{$prefix}/session-{$n}-boiler-draft.json";
    $disk->put($path, json_encode($s->boiler_draft, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "uploaded {$path}: " . $disk->size($path) . " bytes\n";
  }

  if (!empty($s->boiler_final_cut)) {
    $path = "{$prefix}/session-{$n}-boiler-final-manifest.json";
    $disk->put($path, json_encode($s->boiler_final_cut, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "uploaded {$path}: " . $disk->size($path) . " bytes\n";
  }

  $cut = $s->playableCut;
  if ($cut && $cut->moments->isNotEmpty()) {
    $path = "{$prefix}/session-{$n}-scene-cards.json";
    $disk->put($path, json_encode(
      $cut->moments->sortBy("moment_order")->map(fn ($m) => $m->scene_card)->values(),
      JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    ));
    echo "uploaded {$path}: " . $disk->size($path) . " bytes\n";
  }

  if (!empty($s->runtime_narrator_prompt)) {
    $path = "{$prefix}/session-{$n}-runtime-prompt.txt";
    $disk->put($path, $s->runtime_narrator_prompt);
    echo "uploaded {$path}: " . $disk->size($path) . " bytes\n";
  }
}

echo "\nDownload from bucket File explorer under: {$prefix}/\n";
'
```

**Files written** (only when data exists):

| Bucket path | Contents |
|---|---|
| `full-boiler-output.json` | Complete bundle in one file |
| `session-N-boiler-draft.json` | Phase 3.5 draft (`moment_drafts`, `event_role_map`, budget) |
| `session-N-boiler-final-manifest.json` | Finalize manifest only (`boiler_final_cut`) |
| `session-N-scene-cards.json` | Canonical finalized moment cards from `playable_moments` |
| `session-N-runtime-prompt.txt` | Assembled runtime narrator prompt |

### 6.1 Game evidence bundle (played game → File explorer)

Exports **one player's live game** — full turn transcript, world state, moment footnote, scene-card exit budgets, and narration-log joins (`boiler.moment_turn_committed`, repairs, failures).  
**Not** the adaptation bundle above (drafts / scene cards / runtime prompts from the pipeline).

Run on **Laravel Cloud** (production DB + `storage/logs/narration*.log`). Download from bucket **File explorer** → save locally under `docs/Boiler/Debug/`.

#### What to change (only these inputs)

| You know | Set in command | Example |
|---|---|---|
| Email + story slug | `$player` + `$slug` | `$player="thomas@lorespinner.com"` `$slug="pjs"` |
| Username + story slug | `$player` + `$slug` | `$player="curt"` `$slug="wasteland"` |
| Game ULID only | `$gid` in Step B | from play URL `/user/games/{game_id}` or Step A |

`$player` accepts **email or username** — whichever you have.  
`$slug` is the story slug (`pjs`, `wasteland`, `the-adventure-of-the-speckled-band`, …).  
**Latest game** = most recently `updated_at` row for that user + story.

Output filename: `exports/{username-or-email-local}-{slug}-{game_id}-evidence.json` (auto-derived).

---

#### Recommended — find latest + export (one command)

Change **`$player`** and **`$slug`** only. Paste into **Laravel Cloud → Commands**.

```bash
php artisan tinker --execute='try{$player="curt";$slug="wasteland";$u=App\Models\User::query()->where("email",$player)->orWhere("username",$player)->firstOrFail();$s=App\Models\Story::where("slug",$slug)->firstOrFail();$g=$u->games()->where("story_id",$s->id)->orderByDesc("updated_at")->first();if(!$g){echo "NO_GAME player={$player} slug={$slug}\n";return;}$gid=$g->id;$label=($u->username?:explode("@",$u->email)[0])."-".$slug;$g=App\Models\Game::with(["user","story","currentPlayableMoment","currentPlayableSessionCut.moments"])->findOrFail($gid);$cm=$g->currentPlayableMoment;$cut=$g->currentPlayableSessionCut;$moments=[];if($cut){foreach($cut->moments()->orderBy("moment_order")->get() as $m){$c=is_array($m->scene_card)?$m->scene_card:[];$moments[]=["id"=>$m->id,"moment_key"=>$m->moment_key,"moment_order"=>$m->moment_order,"anchor_event_id"=>$m->anchor_event_id,"title"=>$c["title"]??null,"exit_condition"=>$c["exit_condition"]??null,"next_moment_key"=>$c["next_moment_key"]??null,"turn_budget"=>$c["turn_budget"]??null,"word_budget"=>$c["word_budget"]??null,"choice_ids"=>$c["choice_ids"]??null,"is_current"=>($m->id===$g->current_playable_moment_id)];}}$commits=[];$counts=[];$repairs=[];$failures=[];$files=[];foreach(glob(storage_path("logs/narration*.log"))?:[] as $file){$files[]=basename($file);$fh=fopen($file,"r");if(!$fh)continue;while(($line=fgets($fh))!==false){if(!str_contains($line,$gid))continue;if(!preg_match("/\\] (?:production|local|staging)\\.(INFO|WARNING|ERROR|DEBUG): ([\\w\\.]+) /",$line,$mm))continue;$type=$mm[2];$start=strpos($line,"{");$payload=null;if($start!==false){$decoded=json_decode(substr($line,$start),true);if(is_array($decoded))$payload=$decoded;}if(is_array($payload)&&isset($payload["game_id"])&&$payload["game_id"]!==$gid)continue;$counts[$type]=($counts[$type]??0)+1;$at=preg_match("/^\\[([^\\]]+)\\]/",$line,$tm)?$tm[1]:null;if($type==="boiler.moment_turn_committed"&&is_array($payload)){$sid=(string)($payload["source_prompt_id"]??"");if($sid!==""){$commits[$sid]=["logged_at"=>$at,"moment_key"=>$payload["moment_key"]??null,"exit_condition_met"=>$payload["exit_condition_met"]??null,"result"=>$payload["result"]??null,"next_moment_key"=>$payload["next_moment_key"]??null,"moment_footnote"=>$payload["moment_footnote"]??null,"anchor_event_id"=>$payload["anchor_event_id"]??null];}}$bad=str_contains($type,"repair")||str_contains($type,"defaulted")||str_contains($type,"demoted")||str_contains($type,"healed")||str_contains($type,"held")||str_contains($type,"stripped")||str_contains($type,"truncated")||str_contains($type,"mismatch");if($bad)$repairs[]=["at"=>$at,"event"=>$type,"payload"=>$payload];if(str_ends_with($type,"_failed"))$failures[]=["at"=>$at,"event"=>$type,"payload"=>$payload];}fclose($fh);}$turns=[];$joined=0;foreach($g->prompts()->orderBy("created_at")->get() as $i=>$p){$resp=strip_tags((string)$p->response);$sid=$p->source_prompt_id;$c=(is_string($sid)&&isset($commits[$sid]))?$commits[$sid]:null;if($c)$joined++;$turns[]=["n"=>$i+1,"id"=>$p->id,"at"=>(string)$p->created_at,"session"=>$p->session_number,"player"=>$p->prompt,"narrator"=>$resp,"narrator_words"=>str_word_count($resp),"choices"=>$p->choices,"source_prompt_id"=>$sid,"moment_key"=>$c["moment_key"]??null,"exit_condition_met"=>$c["exit_condition_met"]??null,"moment_result"=>$c["result"]??null,"next_moment_key"=>$c["next_moment_key"]??null,"moment_footnote"=>$c["moment_footnote"]??null,"log_at"=>$c["logged_at"]??null];}$pack=["exported_at"=>now()->toIso8601String(),"game"=>["id"=>$g->id,"user"=>$g->user->email,"story"=>$g->story->slug,"updated_at"=>(string)$g->updated_at,"session"=>$g->current_session_number,"complete"=>(bool)$g->current_session_complete,"cut_id"=>$g->current_playable_session_cut_id,"moment_id"=>$g->current_playable_moment_id,"moment_key"=>$cm?$cm->moment_key:null,"moment_order"=>$cm?$cm->moment_order:null,"playable_moment_turn_count"=>$g->playable_moment_turn_count,"footnote"=>$g->moment_footnote,"path"=>$g->defining_path_label,"choice_id"=>$g->defining_choice_id,"choice_line"=>$g->defining_choice_line,"world_state"=>$g->world_state,"alignment"=>$g->alignment_scaffold,"symbolic_memory"=>$g->symbolic_memory],"moments"=>$moments,"logs"=>["files_scanned"=>$files,"counts_by_type"=>$counts,"commits_joined_to_turns"=>$joined,"repair_count"=>count($repairs),"failure_count"=>count($failures),"repairs"=>$repairs,"failures"=>$failures],"turns"=>$turns];$json=json_encode($pack,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);if($json===false)throw new RuntimeException("json_encode failed");$path="exports/{$label}-{$gid}-evidence.json";Illuminate\Support\Facades\Storage::disk("public")->put($path,$json);echo "OK player={$player} slug={$slug} game_id={$gid}\n";echo "OK uploaded={$path}\n";echo "bytes=".strlen($json)." turns=".count($turns)." joined={$joined} repairs=".count($repairs)." failures=".count($failures)."\n";echo "moment=".($cm?$cm->moment_key:"null")." turn_count=".$g->playable_moment_turn_count."\n";}catch(Throwable $e){echo "ERROR ".$e::class.": ".$e->getMessage()."\n".$e->getFile().":".$e->getLine()."\n";}'
```

**Quick recipes** — same command, change the two lines at the top:

| Request | `$player` | `$slug` |
|---|---|---|
| Curt's latest Wasteland | `"curt"` | `"wasteland"` |
| Thomas's latest PJ's | `"thomas@lorespinner.com"` | `"pjs"` |
| Thomas's latest Speckled Band | `"lorespinner"` | `"the-adventure-of-the-speckled-band"` |

---

#### Step A — lookup only (verify before export)

Use when you want the `game_id` first, or when the one-shot returns `NO_GAME`.

```bash
php artisan tinker --execute='$player="curt";$slug="wasteland";$u=App\Models\User::query()->where("email",$player)->orWhere("username",$player)->firstOrFail();$s=App\Models\Story::where("slug",$slug)->firstOrFail();$g=$u->games()->where("story_id",$s->id)->orderByDesc("updated_at")->first();if(!$g){echo "NO_GAME player={$player} slug={$slug}\n";return;}echo "game_id=".$g->id."\n";echo "user=".$u->email." (@".$u->username.")\n";echo "story=".$s->slug."\n";echo "updated_at=".$g->updated_at."\n";echo "session=".$g->current_session_number."\n";echo "prompts=".$g->prompts()->count()."\n";'
```

#### Step A2 — list recent games (multiple saves / pick wrong one)

When a player has **reset or replayed** the same story, list the last five and take the top row (or copy a specific `game_id` into Step B).

```bash
php artisan tinker --execute='$player="curt";$slug="wasteland";$u=App\Models\User::query()->where("email",$player)->orWhere("username",$player)->firstOrFail();$s=App\Models\Story::where("slug",$slug)->firstOrFail();$games=$u->games()->where("story_id",$s->id)->orderByDesc("updated_at")->limit(5)->get();if($games->isEmpty()){echo "NO_GAME player={$player} slug={$slug}\n";return;}foreach($games as $i=>$g){echo ($i===0?"LATEST ":"")."game_id={$g->id} updated={$g->updated_at} session={$g->current_session_number} prompts=".$g->prompts()->count()."\n";}'
```

#### Step B — export by `game_id` (skip lookup)

Use when you already have the ULID (from Step A, Step A2, or the play URL).  
Change **`$gid`** only; `$label` is optional (defaults from the game record).

```bash
php artisan tinker --execute='try{$gid="PASTE_GAME_ULID_HERE";$g=App\Models\Game::with(["user","story","currentPlayableMoment","currentPlayableSessionCut.moments"])->findOrFail($gid);$label=($g->user->username?:explode("@",$g->user->email)[0])."-".$g->story->slug;$cm=$g->currentPlayableMoment;$cut=$g->currentPlayableSessionCut;$moments=[];if($cut){foreach($cut->moments()->orderBy("moment_order")->get() as $m){$c=is_array($m->scene_card)?$m->scene_card:[];$moments[]=["id"=>$m->id,"moment_key"=>$m->moment_key,"moment_order"=>$m->moment_order,"anchor_event_id"=>$m->anchor_event_id,"title"=>$c["title"]??null,"exit_condition"=>$c["exit_condition"]??null,"next_moment_key"=>$c["next_moment_key"]??null,"turn_budget"=>$c["turn_budget"]??null,"word_budget"=>$c["word_budget"]??null,"choice_ids"=>$c["choice_ids"]??null,"is_current"=>($m->id===$g->current_playable_moment_id)];}}$commits=[];$counts=[];$repairs=[];$failures=[];$files=[];foreach(glob(storage_path("logs/narration*.log"))?:[] as $file){$files[]=basename($file);$fh=fopen($file,"r");if(!$fh)continue;while(($line=fgets($fh))!==false){if(!str_contains($line,$gid))continue;if(!preg_match("/\\] (?:production|local|staging)\\.(INFO|WARNING|ERROR|DEBUG): ([\\w\\.]+) /",$line,$mm))continue;$type=$mm[2];$start=strpos($line,"{");$payload=null;if($start!==false){$decoded=json_decode(substr($line,$start),true);if(is_array($decoded))$payload=$decoded;}if(is_array($payload)&&isset($payload["game_id"])&&$payload["game_id"]!==$gid)continue;$counts[$type]=($counts[$type]??0)+1;$at=preg_match("/^\\[([^\\]]+)\\]/",$line,$tm)?$tm[1]:null;if($type==="boiler.moment_turn_committed"&&is_array($payload)){$sid=(string)($payload["source_prompt_id"]??"");if($sid!==""){$commits[$sid]=["logged_at"=>$at,"moment_key"=>$payload["moment_key"]??null,"exit_condition_met"=>$payload["exit_condition_met"]??null,"result"=>$payload["result"]??null,"next_moment_key"=>$payload["next_moment_key"]??null,"moment_footnote"=>$payload["moment_footnote"]??null,"anchor_event_id"=>$payload["anchor_event_id"]??null];}}$bad=str_contains($type,"repair")||str_contains($type,"defaulted")||str_contains($type,"demoted")||str_contains($type,"healed")||str_contains($type,"held")||str_contains($type,"stripped")||str_contains($type,"truncated")||str_contains($type,"mismatch");if($bad)$repairs[]=["at"=>$at,"event"=>$type,"payload"=>$payload];if(str_ends_with($type,"_failed"))$failures[]=["at"=>$at,"event"=>$type,"payload"=>$payload];}fclose($fh);}$turns=[];$joined=0;foreach($g->prompts()->orderBy("created_at")->get() as $i=>$p){$resp=strip_tags((string)$p->response);$sid=$p->source_prompt_id;$c=(is_string($sid)&&isset($commits[$sid]))?$commits[$sid]:null;if($c)$joined++;$turns[]=["n"=>$i+1,"id"=>$p->id,"at"=>(string)$p->created_at,"session"=>$p->session_number,"player"=>$p->prompt,"narrator"=>$resp,"narrator_words"=>str_word_count($resp),"choices"=>$p->choices,"source_prompt_id"=>$sid,"moment_key"=>$c["moment_key"]??null,"exit_condition_met"=>$c["exit_condition_met"]??null,"moment_result"=>$c["result"]??null,"next_moment_key"=>$c["next_moment_key"]??null,"moment_footnote"=>$c["moment_footnote"]??null,"log_at"=>$c["logged_at"]??null];}$pack=["exported_at"=>now()->toIso8601String(),"game"=>["id"=>$g->id,"user"=>$g->user->email,"story"=>$g->story->slug,"updated_at"=>(string)$g->updated_at,"session"=>$g->current_session_number,"complete"=>(bool)$g->current_session_complete,"cut_id"=>$g->current_playable_session_cut_id,"moment_id"=>$g->current_playable_moment_id,"moment_key"=>$cm?$cm->moment_key:null,"moment_order"=>$cm?$cm->moment_order:null,"playable_moment_turn_count"=>$g->playable_moment_turn_count,"footnote"=>$g->moment_footnote,"path"=>$g->defining_path_label,"choice_id"=>$g->defining_choice_id,"choice_line"=>$g->defining_choice_line,"world_state"=>$g->world_state,"alignment"=>$g->alignment_scaffold,"symbolic_memory"=>$g->symbolic_memory],"moments"=>$moments,"logs"=>["files_scanned"=>$files,"counts_by_type"=>$counts,"commits_joined_to_turns"=>$joined,"repair_count"=>count($repairs),"failure_count"=>count($failures),"repairs"=>$repairs,"failures"=>$failures],"turns"=>$turns];$json=json_encode($pack,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);if($json===false)throw new RuntimeException("json_encode failed");$path="exports/{$label}-{$gid}-evidence.json";Illuminate\Support\Facades\Storage::disk("public")->put($path,$json);echo "OK uploaded={$path}\n";echo "bytes=".strlen($json)." turns=".count($turns)." joined={$joined} repairs=".count($repairs)." failures=".count($failures)."\n";echo "moment=".($cm?$cm->moment_key:"null")." turn_count=".$g->playable_moment_turn_count."\n";}catch(Throwable $e){echo "ERROR ".$e::class.": ".$e->getMessage()."\n".$e->getFile().":".$e->getLine()."\n";}'
```

---

#### Worked example — Thomas / PJ's (2026-08-16)

Same as the recommended command with `$player="thomas@lorespinner.com"` `$slug="pjs"`. Verified output:

| Field | Value |
|---|---|
| `game_id` | `01kww7cqt1cv030aybbnzqdktq` |
| `user` | `thomas@lorespinner.com` |
| `story` | `pjs` |
| `session` | 2 |
| `prompts` | 40 |
| bucket file | `exports/lorespinner-pjs-01kww7cqt1cv030aybbnzqdktq-evidence.json` |

```text
OK player=thomas@lorespinner.com slug=pjs game_id=01kww7cqt1cv030aybbnzqdktq
OK uploaded=exports/lorespinner-pjs-01kww7cqt1cv030aybbnzqdktq-evidence.json
bytes=... turns=40 joined=... repairs=... failures=...
moment=S2_M?? turn_count=...
```

**Bundle shape:**

| Key | Contents |
|---|---|
| `game` | session, footnote, world_state, path/choice, moment position |
| `moments[]` | scene cards, exit budgets, current moment flag |
| `turns[]` | player + narrator transcript per turn, choices, log joins |
| `logs` | narration files scanned, repair/failure events, `commits_joined_to_turns` |

- `joined > 0` → turns include `moment_key` / `moment_footnote` from narration logs  
- `joined = 0` → log disk empty or rotated; transcript + `game.footnote` + `moments[]` still export  

If stdout is blank, Cloud mangled the paste — use Step A + Step B separately, or see `../../../../Adaptation layer/Boiler Adaptation/Boiled Speckeld band /CLOUD-EXPORT-THOMAS-EVIDENCE.md`.

### 6.2 Recent plays & game lookup (QA)

For systematic QA review — who played what, when. Full workflow: `docs/BOILER-QA-PLAYBOOK.md`.

**Recent plays (all stories):**

```bash
php artisan tinker --execute='$limit=20;$rows=App\Models\Game::query()->with(["user","story"])->where("is_preview",false)->orderByDesc("updated_at")->limit($limit)->get();foreach($rows as $g){echo $g->updated_at." | ".$g->id." | ".($g->user->email??"?")." (@".($g->user->username??"?").") | ".$g->story->slug." | S".$g->current_session_number." | prompts=".$g->prompts()->count()."\n";}'
```

**Recent plays on one story** — set `$slug`:

```bash
php artisan tinker --execute='$slug="wasteland";$limit=10;$s=App\Models\Story::where("slug",$slug)->firstOrFail();$rows=App\Models\Game::query()->with("user")->where("story_id",$s->id)->where("is_preview",false)->orderByDesc("updated_at")->limit($limit)->get();foreach($rows as $g){echo $g->updated_at." | ".$g->id." | ".($g->user->email??"?")." (@".($g->user->username??"?").") | prompts=".$g->prompts()->count()." | S".$g->current_session_number."\n";}'
```

**Recent plays by one reviewer** — set `$player` (email or username):

```bash
php artisan tinker --execute='$player="curt";$limit=10;$u=App\Models\User::query()->where("email",$player)->orWhere("username",$player)->firstOrFail();$rows=$u->games()->with("story")->where("is_preview",false)->orderByDesc("updated_at")->limit($limit)->get();foreach($rows as $g){echo $g->updated_at." | ".$g->id." | ".$g->story->slug." | prompts=".$g->prompts()->count()." | S".$g->current_session_number."\n";}'
```

**Who owns a `game_id`?** — set `$gid`:

```bash
php artisan tinker --execute='$gid="PASTE_GAME_ULID";$g=App\Models\Game::with(["user","story","currentPlayableMoment"])->find($gid);if(!$g){echo "NOT_FOUND\n";return;}echo "game_id=".$g->id."\n";echo "email=".$g->user->email."\n";echo "username=".($g->user->username??"(none)")."\n";echo "name=".trim(($g->user->first_name??"")." ".($g->user->last_name??""))."\n";echo "story=".$g->story->slug."\n";echo "updated_at=".$g->updated_at."\n";echo "session=".$g->current_session_number."\n";echo "moment=".($g->currentPlayableMoment?->moment_key??"null")."\n";echo "prompts=".$g->prompts()->count()."\n";'
```

### 6.3 Boiler estimated playtime (all stories)

Story-wide **editorial targets** from `story_adaptations.story_session_map.boiling_profile` — produced by the Story Session Map agent at adaptation time. **Not measured playtime.** Use this to compare catalog length, spot stories that may run long, and sanity-check pacing before launch.

**Reading the output:**

| Column | Meaning |
|---|---|
| `TARGET` | Per-story total: `target_total_playtime_minutes` (all sessions combined) |
| `SESSION MIN` | Sum of per-session `target_playtime_minutes` (should ≈ `TARGET`) |
| `SESS` | Session count from `per_session_guidance` |
| `MISSING` | `boiler_enabled=true` but Story Session Map has not produced `boiling_profile` yet |
| `GRAND TOTAL` | Sum of all per-story `TARGET` values that have a profile |

Example: `the-adventure-of-the-speckled-band` with `TARGET=48m` and `SESS=2` means ~48 minutes for the **whole story** (~24m/session), not 48m per session.

**All boiler-enabled stories (summary table + grand total):**

```bash
php artisan tinker --execute='
$rows = App\Models\StoryAdaptation::query()
  ->where("boiler_enabled", true)
  ->with("story")
  ->get()
  ->sortBy(fn ($a) => $a->story?->slug ?? "");

$grandTotal = 0;
$withProfile = 0;
$missingProfile = 0;

echo "=== BOILER ESTIMATED PLAYTIME (all boiler_enabled stories) ===\n\n";
echo str_pad("SLUG", 42) . str_pad("STATUS", 18) . str_pad("COMPACT", 10) . str_pad("SESS", 5) . str_pad("TARGET", 8) . "SESSION MIN\n";
echo str_repeat("-", 110) . "\n";

foreach ($rows as $a) {
  $story = $a->story;
  $slug = $story?->slug ?? "(no story)";
  $status = $a->adaptation_status->value ?? "?";
  $profile = data_get($a->story_session_map, "boiling_profile");
  $compaction = data_get($profile, "compaction_level", "—");
  $targetTotal = data_get($profile, "target_total_playtime_minutes");
  $guidance = collect(data_get($profile, "per_session_guidance", []));
  $sessionCount = $guidance->count();
  $sessionSum = $guidance->sum("target_playtime_minutes");

  if ($targetTotal === null) {
    $missingProfile++;
    $targetStr = "MISSING";
    $sessionStr = "—";
  } else {
    $withProfile++;
    $grandTotal += (int) $targetTotal;
    $targetStr = (string) $targetTotal . "m";
    $sessionStr = $sessionSum > 0 ? (string) $sessionSum . "m" : "—";
  }

  echo str_pad($slug, 42)
    . str_pad($status, 18)
    . str_pad((string) $compaction, 10)
    . str_pad((string) $sessionCount, 5)
    . str_pad($targetStr, 8)
    . $sessionStr . "\n";
}

echo str_repeat("-", 110) . "\n";
echo "stories with boiler_enabled: " . $rows->count() . "\n";
echo "with boiling_profile:        " . $withProfile . "\n";
echo "missing boiling_profile:     " . $missingProfile . " (adaptation not through Story Session Map yet)\n";
echo "GRAND TOTAL (target minutes): " . $grandTotal . "m (~" . round($grandTotal / 60, 1) . "h)\n";
echo "\nNOTE: These are AI-estimated editorial targets from Story Session Map, not measured playtime.\n";
'
```

**Completed adaptations only** (compact list with per-session breakdown):

```bash
php artisan tinker --execute='
$rows = App\Models\StoryAdaptation::query()
  ->where("boiler_enabled", true)
  ->where("adaptation_status", "completed")
  ->with("story")
  ->get()
  ->sortBy(fn ($a) => $a->story?->slug ?? "");

$grandTotal = 0;

echo "=== BOILER ESTIMATED PLAYTIME (completed only) ===\n\n";

foreach ($rows as $a) {
  $slug = $a->story?->slug ?? "?";
  $profile = data_get($a->story_session_map, "boiling_profile");
  $target = data_get($profile, "target_total_playtime_minutes");
  $compaction = data_get($profile, "compaction_level", "—");
  $sessions = collect(data_get($profile, "per_session_guidance", []))
    ->sortBy("session_number")
    ->map(fn ($g) => "S" . ($g["session_number"] ?? "?") . "=" . ($g["target_playtime_minutes"] ?? "?") . "m")
    ->implode(", ");

  if ($target === null) {
    echo "{$slug}: MISSING boiling_profile\n";
    continue;
  }

  $grandTotal += (int) $target;
  echo str_pad($slug, 42) . str_pad((string) $compaction, 10) . str_pad((string) $target . "m", 8) . $sessions . "\n";
}

echo "\nGRAND TOTAL: {$grandTotal}m (~" . round($grandTotal / 60, 1) . "h) across " . $rows->count() . " completed boiler stories\n";
'
```

**One story — per-session breakdown** (change `$slug`):

```bash
php artisan tinker --execute='
$slug = "the-adventure-of-the-speckled-band";
$story = App\Models\Story::where("slug", $slug)->firstOrFail();
$a = $story->adaptation;

echo "story: {$story->title}\n";
echo "boiler_enabled: " . ($a->boiler_enabled ? "yes" : "no") . "\n";
echo "adaptation_status: " . $a->adaptation_status->value . "\n\n";

$profile = data_get($a->story_session_map, "boiling_profile");
if (! $profile) { echo "MISSING boiling_profile\n"; return; }

echo "compaction_level: " . ($profile["compaction_level"] ?? "?") . "\n";
echo "target_total_playtime_minutes: " . ($profile["target_total_playtime_minutes"] ?? "?") . "\n";
echo "diagnosis: " . Illuminate\Support\Str::limit($profile["diagnosis"] ?? "", 200) . "\n\n";

$sum = 0;
foreach (collect($profile["per_session_guidance"] ?? [])->sortBy("session_number") as $g) {
  $n = $g["session_number"] ?? "?";
  $mins = $g["target_playtime_minutes"] ?? 0;
  $minMom = $g["recommended_moment_count_min"] ?? "?";
  $maxMom = $g["recommended_moment_count_max"] ?? "?";
  $sum += (int) $mins;
  $sa = $a->sessionAdaptations()->where("session_number", $n)->first();
  $moments = $sa?->playableCut?->moments()->count() ?? 0;
  $boiler = $sa?->boiler_status->value ?? "—";
  echo "S{$n}: {$mins}m | moments {$minMom}-{$maxMom} | finalized={$moments} | boiler_status={$boiler}\n";
}

echo "\nsession sum: {$sum}m\n";
'
```

**Upload summary JSON to bucket** (optional — download from File explorer):

```bash
php artisan tinker --execute='
$rows = App\Models\StoryAdaptation::query()
  ->where("boiler_enabled", true)
  ->with("story")
  ->get()
  ->sortBy(fn ($a) => $a->story?->slug ?? "");

$pack = [
  "exported_at" => now()->toIso8601String(),
  "note" => "AI-estimated editorial targets from boiling_profile — not measured playtime",
  "stories" => $rows->map(function ($a) {
    $profile = data_get($a->story_session_map, "boiling_profile");
    $guidance = collect(data_get($profile, "per_session_guidance", []));
    return [
      "story_id" => $a->story_id,
      "slug" => $a->story?->slug,
      "title" => $a->story?->title,
      "adaptation_status" => $a->adaptation_status->value,
      "compaction_level" => data_get($profile, "compaction_level"),
      "target_total_playtime_minutes" => data_get($profile, "target_total_playtime_minutes"),
      "per_session_minutes_sum" => $guidance->sum("target_playtime_minutes"),
      "per_session_guidance" => $guidance->sortBy("session_number")->values(),
    ];
  })->values(),
  "grand_total_minutes" => $rows->sum(fn ($a) => (int) data_get($a->story_session_map, "boiling_profile.target_total_playtime_minutes", 0)),
];

$json = json_encode($pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$dest = "exports/boiler-playtime-summary.json";
Illuminate\Support\Facades\Storage::disk("public")->put($dest, $json);
echo "uploaded {$dest}: " . strlen($json) . " bytes\n";
echo "grand_total_minutes=" . $pack["grand_total_minutes"] . "\n";
'
```

### Boiler — upload straight from DB (skip local `adaptation:export`)

Use when `database/exports/` is empty or was wiped by deploy. Same destination pattern as above; only the combined JSON:

```bash
php artisan tinker --execute='
$slug = "the-adventure-of-the-speckled-band";
$dest = "exports/speckled-band-adaptation.json";
$story = App\Models\Story::where("slug", $slug)->firstOrFail();
$a = $story->adaptation->load("sessionAdaptations");
$json = json_encode([
  "story" => ["id" => $story->id, "title" => $story->title, "slug" => $story->slug],
  "exported_at" => now()->toIso8601String(),
  "adaptation_status" => $a->adaptation_status->value,
  "boiler_enabled" => (bool) $a->boiler_enabled,
  "story_wide" => [
    "format_detection" => $a->format_detection,
    "ip_audit" => $a->ip_audit,
    "voice_profile" => $a->voice_profile,
    "story_session_map" => $a->story_session_map,
  ],
  "sessions" => $a->sessionAdaptations->sortBy("session_number")->map(fn ($s) => [
    "session_number" => $s->session_number,
    "session_status" => $s->session_status->value,
    "boiler_status" => $s->boiler_status->value,
    "boiler_error" => $s->boiler_error,
    "boiler_draft" => $s->boiler_draft,
    "boiler_final_cut" => $s->boiler_final_cut,
    "editorial_verification" => $s->editorial_verification,
    "runtime_narrator_prompt" => $s->runtime_narrator_prompt,
  ])->values(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "source: " . strlen($json) . " bytes\n";
Illuminate\Support\Facades\Storage::disk("public")->put($dest, $json);
echo "uploaded: " . Illuminate\Support\Facades\Storage::disk("public")->size($dest) . " bytes\n";
'
```

### Boiler — quick probes (stdout / no bucket)

**Status grid:**

```bash
php artisan stories:boiler-status the-adventure-of-the-speckled-band
```

### Boiler — rebuild runtime narrator prompts (after template deploy)

Every edit to `runtime-narrator-template.blade.php` (agency rules, dialogue, pacing, etc.) only reaches players once the **cached** prompt on `session_adaptations.runtime_narrator_prompt` is rebuilt. This command re-runs `RuntimeNarratorAssemblyJob` only — it does **not** re-run adaptation, voice lock, Boiler draft/finalize, or scene cards. Authored content is untouched.

- In-flight games pick up the new prompt on their **next turn**.
- A failed session is reported and skipped; other stories/sessions still run (unlike a raw tinker loop that aborts on first throw).
- Failed sessions keep their **previous** prompt and stay playable until you fix and re-run.

**Dry run** — list current sizes, touch nothing:

```bash
php artisan stories:rebuild-runtime-prompts --dry-run
```

**All Boiler stories** (every story with `boiler_enabled = true`):

```bash
php artisan stories:rebuild-runtime-prompts
```

**Launch Boiler stories** (Sherlock, PJs, Wasteland, Treasure Island):

```bash
php artisan stories:rebuild-runtime-prompts \
  the-adventure-of-the-speckled-band \
  pjs \
  wasteland \
  treasure-island
```

**One story / one session:**

```bash
php artisan stories:rebuild-runtime-prompts the-adventure-of-the-speckled-band
php artisan stories:rebuild-runtime-prompts wasteland --session=2
```

Expect a table: story slug, session number, `before → after` prompt char count, and `completed` or `FAILED: …`.

After a successful rebuild, confirm with status grid:

```bash
php artisan stories:boiler-status the-adventure-of-the-speckled-band
```

Optional smoke check — new agency / dialogue blocks present in session 1:

```bash
php artisan tinker --execute='
$rp = App\Models\Story::where("slug","the-adventure-of-the-speckled-band")->first()
  ->adaptation->sessionAdaptations()->where("session_number",1)->value("runtime_narrator_prompt");
echo "chars: " . strlen($rp) . PHP_EOL;
foreach ([
  "AGENCY HANDOFF",
  "CONSEQUENCE HAS A CURRENCY",
  "THE WAY BACK IS NOT A RESET",
  "DIALOGUE — PUNCTUATION IS NOT OPTIONAL",
  "DIALOGUE — A CHARACTER SPEAKS AS",
] as $needle) {
  echo $needle . ": " . (str_contains($rp, $needle) ? "yes" : "NO") . PHP_EOL;
}
'
```

**Opus / model diagnostic dump** (sessions, SSM, failures, queue, `boiler.*` logs → File explorer):

```bash
php artisan stories:boiler-diagnose wasteland
```

Writes `exports/{slug}-boiler-opus-diagnostic.json` on the `public` disk.

**Session pipeline (what phase produced what):**

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","the-adventure-of-the-speckled-band")->firstOrFail(); $a=$story->adaptation; foreach($a->sessionAdaptations()->orderBy("session_number")->get() as $sa){ $cut=$sa->playableCut()->withCount(["moments","eventRoles"])->first(); echo "session=".$sa->session_number." session_status=".$sa->session_status->value." boiler_status=".$sa->boiler_status->value." draft=".(!empty($sa->boiler_draft)?"present":"MISSING")." manifest=".(!empty($sa->boiler_final_cut)?"present":"MISSING")." cut=".($cut?->status??"MISSING")." moments=".($cut?->moments_count??0)." roles=".($cut?->event_roles_count??0)." prompt_chars=".strlen((string)$sa->runtime_narrator_prompt).($sa->boiler_error?" ERROR=".$sa->boiler_error:"").PHP_EOL; }'
```

**Draft JSON (one session):**

```bash
php artisan tinker --execute='echo json_encode(App\Models\Story::where("slug","the-adventure-of-the-speckled-band")->firstOrFail()->adaptation->sessionAdaptations()->where("session_number",1)->value("boiler_draft"), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);'
```

**Finalize manifest JSON (one session):**

```bash
php artisan tinker --execute='echo json_encode(App\Models\Story::where("slug","the-adventure-of-the-speckled-band")->firstOrFail()->adaptation->sessionAdaptations()->where("session_number",1)->value("boiler_final_cut"), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);'
```

**Human-readable moment cards (after Finalize):**

```bash
php artisan stories:inspect-boiler the-adventure-of-the-speckled-band --session=1
```

**Pending adaptation jobs:**

```bash
php artisan tinker --execute='echo "adaptation_jobs=".DB::table("jobs")->where("queue","adaptation")->count().PHP_EOL; foreach(DB::table("jobs")->where("queue","adaptation")->orderBy("id")->get(["id","payload","attempts","reserved_at"]) as $j){ $p=json_decode($j->payload,true); echo "id={$j->id} reserved=".($j->reserved_at?"YES":"no")." class=".class_basename($p["displayName"]??"?").PHP_EOL; }'
```

Run adaptation with Boiler enabled:

```bash
php artisan stories:run-adaptation the-adventure-of-the-speckled-band --force --boiler
```

**Uploaded bytes must match source bytes.** Delete 0-byte objects in file explorer before re-uploading.

---

## 7. Troubleshooting

### Pending jobs not moving

```bash
php artisan tinker --execute="
echo 'pending: ' . DB::table('jobs')->where('queue','adaptation')->count() . PHP_EOL;
foreach (DB::table('jobs')->where('queue','adaptation')->get() as \$j) {
  \$p = json_decode(\$j->payload, true);
  echo '  ' . (\$p['displayName'] ?? '?') . ' reserved=' . (\$j->reserved_at ?: 'null') . PHP_EOL;
}
"
```

Release stuck reservations:

```bash
php artisan queue:restart
```

Or:

```bash
php artisan tinker --execute="
DB::table('jobs')->where('queue','adaptation')->whereNotNull('reserved_at')->update(['reserved_at' => null]);
echo 'released';
"
```

### Recent failures

```bash
php artisan tinker --execute='
foreach (DB::table("failed_jobs")->orderByDesc("failed_at")->limit(5)->get() as $f) {
  $p = json_decode($f->payload, true);
  echo "[" . $f->failed_at . "] " . ($p["displayName"] ?? "?") . PHP_EOL;
  echo Illuminate\Support\Str::limit($f->exception, 200) . PHP_EOL . PHP_EOL;
}
'
```

### Common mistakes

| Symptom | Cause | Fix |
|---|---|---|
| `adaptation_status` stays `completed` after `--force` dispatch | Worker not running | Start `database adaptation` worker |
| `AWS_BUCKET: NULL` / `disk("s3")` TypeError | Bucket not attached or not redeployed | Attach bucket on canvas; use `disk("public")` |
| `/storage/exports/...` 404 | `storage:link` does not persist on Cloud | Upload to bucket; download via file explorer |
| 0-byte files in bucket | Failed upload or wrong disk | Verify source size; use `disk("public")` |
| CLI "Command took longer than 30 minutes" | Cloud CLI limit | Background worker continues; check logs + pending count |
| Queue idle after deploy mid-job | Worker killed; job reserved or batch chain stopped | Release reservations; check `failed_jobs`; re-run `--force --boiler` |
| Template deploy but narration behaviour unchanged | Cached `runtime_narrator_prompt` not rebuilt | `php artisan stories:rebuild-runtime-prompts` (see §6) |
| `runtime_narrator_assembly.failed` / session `failed` after rebuild | Prompt over cap or validator error | Check `boiler_error` via `stories:boiler-status`; fix template/cap; re-run rebuild for that slug |

---

## 8. V2.2 runtime prompt smoke check

```bash
php artisan tinker --execute='
$rp = App\Models\Story::where("slug","the-wonderful-wizard-of-oz")->first()->adaptation->sessionAdaptations()->where("session_number",1)->first()->runtime_narrator_prompt;
echo "chars: " . strlen($rp) . PHP_EOL;
echo (str_contains($rp,"COLLLOCATION FINGERPRINT") ? "has_v22_voice: yes\n" : "has_v22_voice: no\n");
echo (str_contains($rp,"300") && str_contains($rp,"350") ? "has_cadence: yes\n" : "has_cadence: no\n");
'
```

---

## Related docs

- `../v2-implementation/validation/pipeline-upgrade-v2-2-validation-runbook.md` — validation gates
- `../v2-implementation/chaos-v2-command-list.md` — broader command list
- `REFERENCE - VOICE PROFILE - L FRANK BAUM v2.1 copy.md` — Oz voice QA baseline
- `../../../../docs/Boiler/Boiler Run/BOILER_VALIDATION_RUNBOOK.md` — Boiler canary validation on Cloud
- `../../../../docs/BOILER-QA-PLAYBOOK.md` — QA review workflow + Cloud commands for log export
- `../../../../Adaptation layer/Boiler Adaptation/Boiled Speckeld band /CLOUD-EXPORT-THOMAS-EVIDENCE.md` — game evidence bundle (Speckled Band example)
