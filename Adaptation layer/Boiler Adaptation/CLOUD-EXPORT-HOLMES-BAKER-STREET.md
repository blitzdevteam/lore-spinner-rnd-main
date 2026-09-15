# Cloud export — Holmes Baker Street adaptations (preserve before Watson re-boil)

**Titles:** Blue Carbuncle + Scandal in Bohemia (boiled as **Holmes** POV — keep this archive)  
**Run on:** Laravel Cloud → **Commands**  
**Download from:** Environment → **File explorer** → bucket → `exports/`  
**Related:** [`V2.2/laravel-cloud-commands.md`](../Chaos%20adaptation/V2.2/laravel-cloud-commands.md) §5–§6, [`DUAL_PROTAGONIST_ADAPTATIONS.md`](../../RnD%20Ideas/Baker-Street/DUAL_PROTAGONIST_ADAPTATIONS.md)

| Story | Slug |
|---|---|
| The Blue Carbuncle | `the-adventure-of-the-blue-carbuncle` |
| A Scandal in Bohemia | `a-scandal-in-bohemia` |

Speckled Band is already Watson — not included here unless you want a duplicate archive.

---

## 0. Status check (both titles)

Paste once; confirms boil is complete before you export.

```bash
php artisan tinker --execute='
foreach ([
  "the-adventure-of-the-blue-carbuncle",
  "a-scandal-in-bohemia",
] as $slug) {
  echo "=== {$slug} ===\n";
  $story = App\Models\Story::where("slug", $slug)->first();
  if (! $story) { echo "STORY: NOT IN DB\n\n"; continue; }
  $a = $story->adaptation;
  if (! $a) { echo "ADAPTATION: none\n\n"; continue; }
  echo "adaptation_status: {$a->adaptation_status->value}\n";
  echo "boiler_enabled: " . ($a->boiler_enabled ? "yes" : "no") . "\n";
  foreach ($a->sessionAdaptations()->orderBy("session_number")->get() as $s) {
    $rp = strlen((string) $s->runtime_narrator_prompt);
    echo "  S{$s->session_number}: {$s->session_status->value} | boiler={$s->boiler_status->value}"
      . " | draft=" . (filled($s->boiler_draft) ? "y" : "n")
      . " | final=" . (filled($s->boiler_final_cut) ? "y" : "n")
      . " | runtime={$rp}ch"
      . ($s->boiler_error ? " | ERROR={$s->boiler_error}" : "")
      . "\n";
  }
  $hint = data_get($a->sessionAdaptations()->where("session_number", 1)->first()?->entry_point_diagnosis, "freeform_input_hint");
  echo "  S1 freeform_input_hint: " . ($hint ?: "(none)") . "\n\n";
}
'
```

Optional grid:

```bash
php artisan stories:boiler-status the-adventure-of-the-blue-carbuncle
php artisan stories:boiler-status a-scandal-in-bohemia
```

---

## 1. Full Boiler bundle → File explorer (recommended)

Uploads **everything the pipeline produced**: combined JSON, per-session drafts, finalize manifests, scene cards, cached runtime prompts.

**One command — both titles:**

```bash
php artisan tinker --execute='
$jobs = [
  ["slug" => "the-adventure-of-the-blue-carbuncle", "prefix" => "exports/blue-carbuncle-holmes-boiler"],
  ["slug" => "a-scandal-in-bohemia", "prefix" => "exports/scandal-in-bohemia-holmes-boiler"],
];
$disk = Illuminate\Support\Facades\Storage::disk("public");

foreach ($jobs as $job) {
  $slug = $job["slug"];
  $prefix = $job["prefix"];
  echo "\n========== {$slug} ==========\n";
  $story = App\Models\Story::where("slug", $slug)->first();
  if (! $story || ! $story->adaptation) {
    echo "SKIP: story or adaptation missing\n";
    continue;
  }
  $a = $story->adaptation->load("sessionAdaptations.playableCut.moments", "sessionAdaptations.playableCut.eventRoles");

  $bundle = [
    "story" => ["id" => $story->id, "title" => $story->title, "slug" => $story->slug],
    "exported_at" => now()->toIso8601String(),
    "playable_protagonist_note" => "Holmes POV — preserved before Watson variant re-boil",
    "system_prompt_character_name" => data_get($story->system_prompt, "character_name"),
    "adaptation_status" => $a->adaptation_status->value,
    "boiler_enabled" => (bool) $a->boiler_enabled,
    "story_wide" => [
      "boiling_profile" => data_get($a->story_session_map, "boiling_profile"),
      "format_detection" => $a->format_detection,
      "ip_audit" => $a->ip_audit,
      "ip_trimming" => $a->ip_trimming,
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
        "runtime_narrator_prompt" => $s->runtime_narrator_prompt,
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
    if (! empty($s->boiler_draft)) {
      $path = "{$prefix}/session-{$n}-boiler-draft.json";
      $disk->put($path, json_encode($s->boiler_draft, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
      echo "uploaded {$path}: " . $disk->size($path) . " bytes\n";
    }
    if (! empty($s->boiler_final_cut)) {
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
    if (! empty($s->runtime_narrator_prompt)) {
      $path = "{$prefix}/session-{$n}-runtime-prompt.txt";
      $disk->put($path, $s->runtime_narrator_prompt);
      echo "uploaded {$path}: " . $disk->size($path) . " bytes\n";
    }
    $cold = data_get($s->entry_point_diagnosis, "cold_open");
    if (filled($cold)) {
      $path = "{$prefix}/session-{$n}-cold-open.txt";
      $disk->put($path, (string) $cold);
      echo "uploaded {$path}: " . $disk->size($path) . " bytes\n";
    }
    $hint = data_get($s->entry_point_diagnosis, "freeform_input_hint");
    if (filled($hint)) {
      $path = "{$prefix}/session-{$n}-freeform-input-hint.txt";
      $disk->put($path, (string) $hint);
      echo "uploaded {$path}: " . $disk->size($path) . " bytes\n";
    }
  }
  echo "Download folder: {$prefix}/\n";
}
'
```

### Files per title (when data exists)

| Bucket path | Contents |
|---|---|
| `full-boiler-output.json` | **Master archive** — story-wide + all sessions + full runtime prompts |
| `session-N-boiler-draft.json` | Boiler draft phase |
| `session-N-boiler-final-manifest.json` | Finalize manifest |
| `session-N-scene-cards.json` | Canonical moment cards |
| `session-N-runtime-prompt.txt` | Cached runtime narrator prompt |
| `session-N-cold-open.txt` | Entry-point cold open prose |
| `session-N-freeform-input-hint.txt` | Chat bar placeholder (S1 especially) |

**Download paths:**

- `exports/blue-carbuncle-holmes-boiler/`
- `exports/scandal-in-bohemia-holmes-boiler/`

Save locally under e.g. `Adaptation layer/Boiler Adaptation/Boiled Holmes/` after download.

---

## 2. Opus diagnostic (pipeline failures / queue)

```bash
php artisan stories:boiler-diagnose the-adventure-of-the-blue-carbuncle
php artisan stories:boiler-diagnose a-scandal-in-bohemia
```

Bucket files:

- `exports/the-adventure-of-the-blue-carbuncle-boiler-opus-diagnostic.json`
- `exports/a-scandal-in-bohemia-boiler-opus-diagnostic.json`

---

## 3. Session 1 injected runtime prompt (what the model sees at open)

Confirms Holmes vs Watson in the **fully injected** opening (not just cached assembly).

**Blue Carbuncle:**

```bash
php artisan tinker --execute='
$slug = "the-adventure-of-the-blue-carbuncle";
$session = 1;
$engine = app(App\Services\ChaosEngineService::class);
$story = App\Models\Story::where("slug", $slug)->firstOrFail();
$ctx = $engine->loadSessionContext($story, $session, null, null, null, null);
if ($ctx === null) { echo "MISSING adaptation\n"; return; }
$rp = $engine->renderSystemPrompt($ctx, $engine->emptyWorldState(), $engine->emptyAlignmentScaffold(), null, true, false);
$dest = "exports/{$slug}-session-{$session}-runtime-injected-holmes.txt";
Illuminate\Support\Facades\Storage::disk("public")->put($dest, $rp);
echo "uploaded {$dest}: " . strlen($rp) . " bytes\n";
foreach (["PROTAGONIST:", "You are", "Holmes", "Watson", "AGENCY HANDOFF"] as $needle) {
  echo $needle . " → " . (str_contains($rp, $needle) ? "yes" : "no") . "\n";
}
'
```

**Scandal in Bohemia** — same block; change `$slug` to `"a-scandal-in-bohemia"`.

---

## 4. Legacy `adaptation:export` + bucket upload

Creates timestamped JSON on server disk, then copies to bucket (if `database/exports/` survives deploy).

**Blue Carbuncle:**

```bash
php artisan adaptation:export the-adventure-of-the-blue-carbuncle
php artisan tinker --execute='
$slug = "the-adventure-of-the-blue-carbuncle";
$dir = database_path("exports");
$files = glob($dir . "/adaptation-{$slug}-*.json");
if (empty($files)) { echo "No export in {$dir}\n"; exit(1); }
usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
$src = $files[0];
$dest = "exports/blue-carbuncle-holmes-adaptation-export.json";
$content = file_get_contents($src);
echo "source: " . basename($src) . " (" . strlen($content) . " bytes)\n";
Illuminate\Support\Facades\Storage::disk("public")->put($dest, $content);
echo "uploaded: " . Illuminate\Support\Facades\Storage::disk("public")->size($dest) . " bytes\n";
'
```

**Scandal in Bohemia** — run `adaptation:export a-scandal-in-bohemia`, then upload to `exports/scandal-in-bohemia-holmes-adaptation-export.json`.

---

## 5. Game evidence bundle (optional — only if someone played on Cloud)

**Not** the adaptation archive — this is a **live play transcript** (turns, world state, moment footnotes).  
Use when QA has a finished or in-progress game to download.

### Find latest game per title

```bash
php artisan tinker --execute='
$slug = "the-adventure-of-the-blue-carbuncle";
$s = App\Models\Story::where("slug", $slug)->firstOrFail();
$rows = App\Models\Game::query()->with("user")->where("story_id", $s->id)->where("is_preview", false)->orderByDesc("updated_at")->limit(5)->get();
foreach ($rows as $g) {
  echo $g->updated_at . " | " . $g->id . " | " . ($g->user->email ?? "?") . " | prompts=" . $g->prompts()->count() . " | S" . $g->current_session_number . "\n";
}
'
```

Repeat with `$slug = "a-scandal-in-bohemia"`.

### Export by game_id

Replace `PASTE_GAME_ULID_HERE` with id from URL `/user/games/{game_id}` or lookup above.  
Full one-liner: [`laravel-cloud-commands.md` §6.1 Step B](../Chaos%20adaptation/V2.2/laravel-cloud-commands.md) (same as Speckled Band [`CLOUD-EXPORT-THOMAS-EVIDENCE.md`](Boiled%20Speckeld%20band%20/CLOUD-EXPORT-THOMAS-EVIDENCE.md)).

Output: `exports/{username}-{slug}-{game_id}-evidence.json`

---

## 6. Download checklist (File explorer)

After §1 runs successfully, grab these folders/files:

| Save locally as | Bucket path |
|---|---|
| Blue Carbuncle master | `exports/blue-carbuncle-holmes-boiler/full-boiler-output.json` |
| Blue Carbuncle folder (all session files) | `exports/blue-carbuncle-holmes-boiler/*` |
| Scandal master | `exports/scandal-in-bohemia-holmes-boiler/full-boiler-output.json` |
| Scandal folder | `exports/scandal-in-bohemia-holmes-boiler/*` |
| Diagnostics (optional) | `exports/the-adventure-of-the-blue-carbuncle-boiler-opus-diagnostic.json` |
| Diagnostics (optional) | `exports/a-scandal-in-bohemia-boiler-opus-diagnostic.json` |

**Minimum for Holmes preservation:** both `full-boiler-output.json` files.

---

## 7. After download — safe to Watson re-boil?

Only after:

1. Both `full-boiler-output.json` files are on your machine (or in repo backup)
2. You have read §1 stdout with non-zero byte counts
3. You have implemented dual-protagonist DB support **or** accept overwriting Holmes row (see RND doc)

**Do not** run `stories:run-adaptation … --force --boiler` until Holmes archives are confirmed.

---

## 8. Quick smoke — protagonist in S1 cold open

After download, grep local `full-boiler-output.json`:

```bash
# Holmes boil should mention Holmes in narration / cold open; Watson may appear as NPC only
jq -r '.sessions[0].entry_point_diagnosis.cold_open' exports/blue-carbuncle-holmes-boiler/full-boiler-output.json | head -20
jq -r '.sessions[0].entry_point_diagnosis.freeform_input_hint' exports/blue-carbuncle-holmes-boiler/full-boiler-output.json
```

Same for `scandal-in-bohemia-holmes-boiler/full-boiler-output.json`.
