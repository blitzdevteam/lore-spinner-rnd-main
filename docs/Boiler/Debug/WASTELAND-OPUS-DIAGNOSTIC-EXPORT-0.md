# Wasteland Boiler — Opus diagnostic export

Paste into **Laravel Cloud → Commands**. Downloads from File explorer:

`exports/wasteland-boiler-opus-diagnostic.json`

Gives Opus: session pipeline state, SSM budgets, choice bindings, draft/cut summaries, `failed_jobs`, queue/batches, and scraped `boiler.*` log lines.

---

```bash
php artisan tinker --execute='
try {
  $slug = "wasteland";
  $story = App\Models\Story::where("slug", $slug)->firstOrFail();
  $storyId = $story->id;
  $a = $story->adaptation()->with([
    "sessionAdaptations.playableCut.moments",
    "sessionAdaptations.playableCut.eventRoles"
  ])->firstOrFail();
  $ssm = $a->story_session_map;
  $alloc = collect(data_get($ssm, "session_allocation", []));

  $sessionPacks = [];
  foreach ($a->sessionAdaptations->sortBy("session_number") as $s) {
    $n = (int) $s->session_number;
    $guidance = $alloc->firstWhere("session_number", $n)
      ?: $alloc->first(function ($row) use ($n) { return (int) data_get($row, "session_number") === $n; });
    $draft = is_array($s->boiler_draft) ? $s->boiler_draft : null;
    $cut = is_array($s->boiler_final_cut) ? $s->boiler_final_cut : null;
    $choices = is_array($s->session_choice_design) ? $s->session_choice_design : null;
    $arch = is_array($s->session_architecture) ? $s->session_architecture : null;

    $momentDrafts = [];
    $sourceIdHits = [];
    foreach ((array) data_get($draft, "moment_drafts", []) as $md) {
      $ids = array_values(array_map("intval", (array) ($md["source_event_ids"] ?? [])));
      foreach ($ids as $eid) { $sourceIdHits[$eid] = ($sourceIdHits[$eid] ?? 0) + 1; }
      $momentDrafts[] = [
        "moment_key" => $md["moment_key"] ?? null,
        "anchor_event_id" => $md["anchor_event_id"] ?? null,
        "source_event_ids" => $ids,
        "role" => $md["role"] ?? ($md["moment_role"] ?? null),
      ];
    }
    $duplicateSourceIds = collect($sourceIdHits)->filter(function($c) { return $c > 1; })->keys()->values()->all();

    $branching = [];
    foreach ((array) data_get($choices, "branching_choices", []) as $c) {
      $branching[] = [
        "choice_id" => $c["choice_id"] ?? null,
        "category" => $c["category"] ?? null,
        "moment_key" => $c["moment_key"] ?? null,
      ];
    }

    $finalMoments = [];
    foreach ((array) data_get($cut, "moments", data_get($cut, "playable_moments", [])) as $fm) {
      $card = $fm["scene_card"] ?? $fm;
      $finalMoments[] = [
        "moment_key" => $fm["moment_key"] ?? ($card["moment_key"] ?? null),
        "choice_ids" => $card["choice_ids"] ?? ($fm["choice_ids"] ?? null),
        "word_budget" => $card["word_budget"] ?? null,
        "turn_budget" => $card["turn_budget"] ?? null,
        "next_moment_key" => $card["next_moment_key"] ?? null,
        "exit_condition_len" => isset($card["exit_condition"]) ? mb_strlen((string) $card["exit_condition"]) : null,
        "affordances_count" => is_array($card["player_affordances"] ?? null) ? count($card["player_affordances"]) : null,
      ];
    }

    $normMoments = [];
    $playable = $s->playableCut;
    if ($playable) {
      foreach ($playable->moments->sortBy("moment_order") as $m) {
        $c = is_array($m->scene_card) ? $m->scene_card : [];
        $normMoments[] = [
          "moment_key" => $m->moment_key,
          "moment_order" => $m->moment_order,
          "anchor_event_id" => $m->anchor_event_id,
          "source_event_ids" => $m->source_event_ids,
          "choice_ids" => $c["choice_ids"] ?? null,
          "word_budget" => $c["word_budget"] ?? null,
          "turn_budget" => $c["turn_budget"] ?? null,
          "next_moment_key" => $c["next_moment_key"] ?? null,
        ];
      }
    }

    $sessionPacks[] = [
      "session_number" => $n,
      "session_status" => $s->session_status->value,
      "boiler_status" => $s->boiler_status?->value,
      "boiler_error" => $s->boiler_error,
      "timestamps" => [
        "boiler_generated_at" => optional($s->boiler_generated_at)?->toIso8601String(),
        "boiler_finalized_at" => optional($s->boiler_finalized_at)?->toIso8601String(),
        "runtime_narrator_assembled_at" => optional($s->runtime_narrator_assembled_at)?->toIso8601String(),
        "updated_at" => optional($s->updated_at)?->toIso8601String(),
      ],
      "artifacts_present" => [
        "entry_point" => !empty($s->entry_point_diagnosis),
        "draft" => !empty($draft),
        "architecture" => !empty($arch),
        "choice_design" => !empty($choices),
        "consequence_map" => !empty($s->choice_consequence_map),
        "session_close" => !empty($s->session_close_design),
        "final_cut" => !empty($cut),
        "editorial" => !empty($s->editorial_verification),
        "runtime_prompt" => !empty($s->runtime_narrator_prompt),
        "normalized_cut" => (bool) $playable,
      ],
      "ssm_guidance" => [
        "event_range" => data_get($guidance, "event_range"),
        "target_playtime_minutes" => data_get($guidance, "target_playtime_minutes") ?? data_get($guidance, "pacing_guidance.target_playtime_minutes"),
        "recommended_moment_count_min" => data_get($guidance, "recommended_moment_count_min") ?? data_get($guidance, "pacing_guidance.recommended_moment_count_min"),
        "recommended_moment_count_max" => data_get($guidance, "recommended_moment_count_max") ?? data_get($guidance, "pacing_guidance.recommended_moment_count_max"),
        "must_compress_event_ids" => data_get($guidance, "must_compress_event_ids") ?? data_get($guidance, "pacing_guidance.must_compress_event_ids"),
        "must_breathe_event_ids" => data_get($guidance, "must_breathe_event_ids") ?? data_get($guidance, "pacing_guidance.must_breathe_event_ids"),
        "raw_allocation_keys" => is_array($guidance) ? array_keys($guidance) : null,
      ],
      "draft_summary" => $draft ? [
        "target_moment_count" => data_get($draft, "session_budget_decision.target_moment_count"),
        "target_playtime_minutes" => data_get($draft, "session_budget_decision.target_playtime_minutes"),
        "moment_draft_count" => count($momentDrafts),
        "moment_drafts" => $momentDrafts,
        "duplicate_source_event_ids" => $duplicateSourceIds,
        "event_role_map_count" => count((array) data_get($draft, "event_role_map", [])),
      ] : null,
      "architecture_branching_slots" => collect((array) data_get($arch, "beat_map", data_get($arch, "beats", [])))
        ->filter(function($b) { return str_contains(strtoupper((string) data_get($b, "choice_slot", data_get($b, "slot", ""))), "BRANCH")
          || data_get($b, "is_branching") === true; })
        ->map(function($b) {
          return [
            "moment_key" => data_get($b, "moment_key"),
            "choice_slot" => data_get($b, "choice_slot", data_get($b, "slot")),
          ];
        })->values()->all(),
      "choice_design_branching" => $branching,
      "final_cut_moment_summaries" => $finalMoments,
      "normalized_moment_summaries" => $normMoments,
      "editorial_verification" => $s->editorial_verification,
      "runtime_prompt_chars" => strlen((string) $s->runtime_narrator_prompt),
    ];
  }

  $failed = [];
  foreach (DB::table("failed_jobs")->orderByDesc("failed_at")->limit(80)->get() as $f) {
    $p = json_decode($f->payload, true) ?: [];
    $dn = (string) ($p["displayName"] ?? "");
    $raw = (string) ($p["data"]["command"] ?? "");
    $matchesStory = str_contains($raw, "\"id\";i:" . $storyId . ";")
      || str_contains($raw, "story_id\";i:" . $storyId . ";")
      || str_contains($f->exception, (string)$storyId)
      || str_contains($f->exception, $slug);
    if (!str_starts_with($dn, "App\\\\Jobs\\\\Adaptation\\\\") && !$matchesStory) {
      continue;
    }
    if (!$matchesStory && !str_contains($raw, $slug) && !str_contains($raw, "Story")) {
      // keep adaptation failures that mention boiler keywords in exception
      if (!preg_match("/Boiler|Editorial|Draft|Finalize|source_event|moment_count|SESSION_END/i", $f->exception)) {
        continue;
      }
    }
    $failed[] = [
      "failed_at" => (string) $f->failed_at,
      "uuid" => $f->uuid,
      "displayName" => $dn,
      "exception_head" => mb_substr(preg_replace("/\s+/", " ", $f->exception), 0, 700),
    ];
    if (count($failed) >= 40) break;
  }

  $jobsNow = [];
  foreach (DB::table("jobs")->where("queue", "adaptation")->orderBy("id")->get() as $j) {
    $p = json_decode($j->payload, true) ?: [];
    $jobsNow[] = [
      "id" => $j->id,
      "attempts" => $j->attempts,
      "reserved_at" => $j->reserved_at,
      "available_at" => $j->available_at,
      "available_in_sec" => max(0, (int)$j->available_at - time()),
      "displayName" => $p["displayName"] ?? null,
    ];
  }

  $batches = [];
  foreach (DB::table("job_batches")->orderByDesc("created_at")->limit(12)->get() as $b) {
    $batches[] = [
      "id" => $b->id,
      "name" => $b->name,
      "total_jobs" => $b->total_jobs,
      "pending_jobs" => $b->pending_jobs,
      "failed_jobs" => $b->failed_jobs,
      "created_at" => $b->created_at,
      "cancelled_at" => $b->cancelled_at,
      "finished_at" => $b->finished_at,
    ];
  }

  $logHits = [];
  $logFiles = [];
  $patterns = ["boiler.", "Adaptation\\", "editorial_verification", "MaxAttemptsExceeded"];
  foreach (array_merge(
    glob(storage_path("logs/laravel*.log")) ?: [],
    glob(storage_path("logs/narration*.log")) ?: []
  ) as $file) {
    $logFiles[] = basename($file);
    $fh = fopen($file, "r");
    if (!$fh) continue;
    while (($line = fgets($fh)) !== false) {
      $keep = false;
      foreach ($patterns as $pat) {
        if (str_contains($line, $pat)) { $keep = true; break; }
      }
      if (!$keep) continue;
      // Prefer story-scoped lines; still keep generic boiler failures
      $scoped = str_contains($line, "\"story_id\":" . $storyId)
        || str_contains($line, "\"story_id\": " . $storyId)
        || str_contains($line, "story_id\":" . $storyId)
        || str_contains($line, (string)$storyId);
      if (!$scoped && !str_contains($line, "boiler.")) {
        continue;
      }
      if (preg_match("/^\[([^\]]+)\]/", $line, $tm)) {
        $at = $tm[1];
      } else {
        $at = null;
      }
      $level = null; $event = null;
      if (preg_match("/\] (?:production|local|staging|cloud)\.(INFO|WARNING|ERROR|DEBUG): ([\w\.]+)/", $line, $mm)) {
        $level = $mm[1];
        $event = $mm[2];
      }
      $payload = null;
      $start = strpos($line, "{");
      if ($start !== false) {
        $decoded = json_decode(substr($line, $start), true);
        if (is_array($decoded)) $payload = $decoded;
      }
      if (is_array($payload) && isset($payload["story_id"]) && (int)$payload["story_id"] !== (int)$storyId) {
        continue;
      }
      $logHits[] = [
        "at" => $at,
        "level" => $level,
        "event" => $event,
        "payload" => $payload,
        "line_head" => mb_substr(preg_replace("/\s+/", " ", $line), 0, 400),
      ];
    }
    fclose($fh);
  }
  // keep newest ~250
  $logHits = array_slice($logHits, -250);

  $eventCount = $story->events()->count();
  $sessionEventCounts = [];
  for ($n = 1; $n <= 4; $n++) {
    $sessionEventCounts[$n] = $story->events()->where("session_number", $n)->count();
  }

  $pack = [
    "brief_for_model" => [
      "goal" => "Diagnose why Wasteland Boiler adaptation keeps failing while Speckled Band (Sherlock) completed cleanly.",
      "slug" => $slug,
      "story_id" => $storyId,
      "known_ops_noise" => [
        "Laravel Cloud deploys killed workers mid-Finalize (attempts inflated → MaxAttemptsExceeded).",
        "Tinker finally-closures orphaned Bus batches (batch pending=36, jobs table empty).",
        "Release reserved jobs MUST reset attempts to 0.",
        "Do not treat MaxAttemptsExceeded as a content bug.",
      ],
      "known_content_failures_seen" => [
        "S1 Finalize: Final scene card S1_M01 has invalid word or turn budgets (target_per_turn/target_turns ints>=1, gravity_after>=target_turns).",
        "S2 Editorial: choice_ids around S2_M07 vs Phase 5 authority; missing persistent state deltas.",
        "S3 Editorial: SESSION_END_HOOK/C4 on final moment; weak playable rooms S3_M05/S3_M09.",
        "S4 Draft: event 2225 assigned to more than one moment; target_moment_count outside SSM range.",
      ],
      "ask" => "Separate ops/retry damage from true prompt/validator defects. Propose the smallest durable code/prompt fixes so Wasteland can complete without endless redispatch. Compare against Speckled Band success pattern if evidence allows.",
    ],
    "exported_at" => now()->toIso8601String(),
    "story" => [
      "id" => $storyId,
      "title" => $story->title,
      "slug" => $slug,
      "event_count_total" => $eventCount,
      "event_count_by_session" => $sessionEventCounts,
    ],
    "adaptation" => [
      "adaptation_status" => $a->adaptation_status->value,
      "boiler_enabled" => (bool) $a->boiler_enabled,
      "has_voice_profile" => !empty($a->voice_profile),
      "has_story_session_map" => !empty($ssm),
      "boiling_profile" => data_get($ssm, "boiling_profile"),
      "session_allocation" => data_get($ssm, "session_allocation"),
    ],
    "queue" => [
      "adaptation_pending" => DB::table("jobs")->where("queue", "adaptation")->whereNull("reserved_at")->count(),
      "adaptation_reserved" => DB::table("jobs")->where("queue", "adaptation")->whereNotNull("reserved_at")->count(),
      "jobs_now" => $jobsNow,
      "recent_batches" => $batches,
      "failed_jobs_matched" => $failed,
      "failed_jobs_table_count" => DB::table("failed_jobs")->count(),
    ],
    "sessions" => $sessionPacks,
    "logs" => [
      "files_scanned" => $logFiles,
      "hit_count" => count($logHits),
      "hits" => $logHits,
    ],
  ];

  $json = json_encode($pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
  if ($json === false) {
    throw new RuntimeException("json_encode failed: " . json_last_error_msg());
  }
  $path = "exports/wasteland-boiler-opus-diagnostic.json";
  Illuminate\Support\Facades\Storage::disk("public")->put($path, $json);
  echo "OK uploaded=" . $path . "\n";
  echo "bytes=" . strlen($json) . " sessions=" . count($sessionPacks) . " failed_matched=" . count($failed) . " log_hits=" . count($logHits) . "\n";
  echo "queue pending=" . $pack["queue"]["adaptation_pending"] . " reserved=" . $pack["queue"]["adaptation_reserved"] . "\n";
  foreach ($sessionPacks as $sp) {
    echo "S" . $sp["session_number"] . " " . $sp["session_status"] . "/" . $sp["boiler_status"] . " draft=" . ($sp["artifacts_present"]["draft"] ? "y" : "n") . " cut=" . ($sp["artifacts_present"]["final_cut"] ? "y" : "n") . "\n";
  }
} catch (Throwable $e) {
  echo "ERROR " . get_class($e) . ": " . $e->getMessage() . "\n" . $e->getFile() . ":" . $e->getLine() . "\n";
}
'
```

---

### Expected stdout

```text
OK uploaded=exports/wasteland-boiler-opus-diagnostic.json
bytes=...
queue pending=... reserved=...
S1 ...
```

Download from Cloud **File explorer** → `exports/wasteland-boiler-opus-diagnostic.json` and give that file to Opus.

### If jobs are pending but not running

Workers are down (common after Deploy). On **Worker Cluster** (not Command UI):

`php artisan queue:work database --queue=adaptation --tries=2 --timeout=900`

Unstick reserved leases:

```bash
php artisan tinker --execute="
\$n = DB::table('jobs')->where('queue','adaptation')->whereNotNull('reserved_at')->update(['reserved_at'=>null,'available_at'=>time(),'attempts'=>0]);
echo 'released='.\$n.' pending='.DB::table('jobs')->where('queue','adaptation')->count().\"\\n\";
"
```
