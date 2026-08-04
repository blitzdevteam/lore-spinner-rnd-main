# Laravel Cloud — V2.2 Adaptation Commands (Verified)

Practical command reference from the **Wizard of Oz** V2.2 re-adaptation on Laravel Cloud (June 2026).  
Replace `the-wonderful-wizard-of-oz` with your story slug where needed.

**Boiler pacing** commands (export bundle, probes, `--boiler` runs) are in **§6** — example slug: `the-adventure-of-the-speckled-band`.

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
| `boiler_status=finalizing` with no manifest/cut | Finalize interrupted (deploy) | Re-run adaptation; do not deploy during long Boiler jobs |

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
