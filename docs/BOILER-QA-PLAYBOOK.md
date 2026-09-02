# Boiler QA Playbook

Systematic review process for every **Boiler-adapted** title before wider launch.

| Doc | Purpose |
|---|---|
| **[BOILER-FUN-SCOREBOARD.md](./BOILER-FUN-SCOREBOARD.md)** | Score rubric (pillars A–F), session sheet, team totals |
| **[Boiler/QA/TITLE-TRACKER.md](./Boiler/QA/TITLE-TRACKER.md)** | Per-title status — who played, evidence, analysis |
| **[Boiler/QA/_ANALYSIS-TEMPLATE.md](./Boiler/QA/_ANALYSIS-TEMPLATE.md)** | Copy for each scored write-up |
| **[Adaptation layer/Chaos adaptation/V2.2/laravel-cloud-commands.md](../Adaptation%20layer/Chaos%20adaptation/V2.2/laravel-cloud-commands.md)** | Full Cloud command reference (§6 exports) |

---

## Reviewers

Each title should get **at least two** independent play sessions before ship. Target roster:

| Reviewer | `$player` (email or username) | Notes |
|---|---|---|
| **Curt** | `curt` | `curtnorthrup@gmail.com` |
| **Thomas** | `thomas@lorespinner.com` or `lorespinner` | Voice / author bar |
| **Mike** | email or username on account | |
| **Tiffany** | email or username on account | |
| **Shervin** | email or username on account | Optional / overflow |

Use whatever identifier matches their LoreSpinner account. The lookup commands accept **email or username**.

---

## Minimum play session (tester)

From the scoreboard — do this **before** exporting:

1. One full session **or** minimum **15 turns** (whichever comes first).
2. Include **5+ freeform typed** actions (not only suggestion buttons).
3. Try at least one **bold off-plan** action and one **repeat intent** (same ask 3 turns).
4. Note **device / browser** on the session sheet.
5. Copy **game ID** from the play URL: `/user/games/{game_id}`.

**Ship bar (scoreboard):** no pillar below **2**; no **0** on Agency (B) or Platform (A).

---

## QA workflow (systematic)

```text
[1] Adaptation complete on Cloud     → stories:boiler-status {slug}
[2] Reviewer plays                   → session sheet in scoreboard
[3] Export evidence bundle           → Laravel Cloud §6.1 (below)
[4] Download JSON                    → docs/Boiler/QA/{slug}/
[5] Scored analysis                  → copy _ANALYSIS-TEMPLATE.md
[6] Update TITLE-TRACKER + scoreboard team totals
[7] Weekly sync                      → compare pillars across reviewers
```

### Pre-flight (before anyone plays)

Run on **Laravel Cloud → Commands**:

```bash
php artisan stories:boiler-status YOUR_STORY_SLUG
```

Expect: all sessions `boiler_status=completed`, `runtime_narrator_prompt` present, moments finalized.

Optional — export adaptation artifacts for diffing:

```bash
php artisan stories:boiler-diagnose YOUR_STORY_SLUG
```

Downloads: `exports/{slug}-boiler-opus-diagnostic.json`

---

## File naming (repo)

Save under **`docs/Boiler/QA/{slug}/`**:

| Artifact | Filename pattern |
|---|---|
| Evidence bundle | `{reviewer}-{slug}-{game_id}-evidence.json` |
| Scored analysis | `{REVIEWER}-{slug}-SCORED-ANALYSIS.md` |
| S1 injected prompt (optional) | `{slug}-session-1-runtime-injected.txt` |
| Session sheet (optional) | `{reviewer}-{slug}-session-sheet.md` |

**Examples (existing):**

- `docs/Boiler/Debug/Thomas log/PJS-THOMAS-SCORED-ANALYSIS.md` — reference write-up
- `docs/Boiler/QA/pjs/thomas-pjs-01kww7cqt1cv030aybbnzqdktq-evidence.json` — preferred location going forward

---

## Laravel Cloud commands (QA cheat sheet)

All commands paste into **Laravel Cloud → Commands**. Download exports from bucket **File explorer**.

Change only the variables called out (`$limit`, `$slug`, `$player`, `$gid`).

### 1 — Recent plays (find what to review)

**All recent activity** (last 20 games, any story):

```bash
php artisan tinker --execute='$limit=20;$rows=App\Models\Game::query()->with(["user","story"])->where("is_preview",false)->orderByDesc("updated_at")->limit($limit)->get();foreach($rows as $g){echo $g->updated_at." | ".$g->id." | ".($g->user->email??"?")." (@".($g->user->username??"?").") | ".$g->story->slug." | S".$g->current_session_number." | prompts=".$g->prompts()->count()."\n";}'
```

**Recent plays on one story** — set `$slug`:

```bash
php artisan tinker --execute='$slug="wasteland";$limit=10;$s=App\Models\Story::where("slug",$slug)->firstOrFail();$rows=App\Models\Game::query()->with("user")->where("story_id",$s->id)->where("is_preview",false)->orderByDesc("updated_at")->limit($limit)->get();foreach($rows as $g){echo $g->updated_at." | ".$g->id." | ".($g->user->email??"?")." (@".($g->user->username??"?").") | prompts=".$g->prompts()->count()." | S".$g->current_session_number."\n";}'
```

**Recent plays by one reviewer** — set `$player`:

```bash
php artisan tinker --execute='$player="curt";$limit=10;$u=App\Models\User::query()->where("email",$player)->orWhere("username",$player)->firstOrFail();$rows=$u->games()->with("story")->where("is_preview",false)->orderByDesc("updated_at")->limit($limit)->get();foreach($rows as $g){echo $g->updated_at." | ".$g->id." | ".$g->story->slug." | prompts=".$g->prompts()->count()." | S".$g->current_session_number."\n";}'
```

### 2 — Who is playing this game?

Set `$gid` from URL or recent-plays output:

```bash
php artisan tinker --execute='$gid="PASTE_GAME_ULID";$g=App\Models\Game::with(["user","story","currentPlayableMoment"])->find($gid);if(!$g){echo "NOT_FOUND\n";return;}echo "game_id=".$g->id."\n";echo "email=".$g->user->email."\n";echo "username=".($g->user->username??"(none)")."\n";echo "name=".trim(($g->user->first_name??"")." ".($g->user->last_name??""))."\n";echo "story=".$g->story->slug."\n";echo "updated_at=".$g->updated_at."\n";echo "session=".$g->current_session_number."\n";echo "moment=".($g->currentPlayableMoment?->moment_key??"null")."\n";echo "prompts=".$g->prompts()->count()."\n";'
```

### 3 — Latest game for reviewer + story (lookup only)

```bash
php artisan tinker --execute='$player="curt";$slug="wasteland";$u=App\Models\User::query()->where("email",$player)->orWhere("username",$player)->firstOrFail();$s=App\Models\Story::where("slug",$slug)->firstOrFail();$g=$u->games()->where("story_id",$s->id)->orderByDesc("updated_at")->first();if(!$g){echo "NO_GAME\n";return;}echo "game_id=".$g->id."\n";echo "user=".$u->email." (@".$u->username.")\n";echo "story=".$s->slug."\n";echo "updated_at=".$g->updated_at."\n";echo "session=".$g->current_session_number."\n";echo "prompts=".$g->prompts()->count()."\n";'
```

### 4 — Export evidence bundle (full play log)

**One command:** find latest for `$player` + `$slug`, export to bucket.

```bash
php artisan tinker --execute='try{$player="curt";$slug="wasteland";$u=App\Models\User::query()->where("email",$player)->orWhere("username",$player)->firstOrFail();$s=App\Models\Story::where("slug",$slug)->firstOrFail();$g=$u->games()->where("story_id",$s->id)->orderByDesc("updated_at")->first();if(!$g){echo "NO_GAME player={$player} slug={$slug}\n";return;}$gid=$g->id;$label=($u->username?:explode("@",$u->email)[0])."-".$slug;$g=App\Models\Game::with(["user","story","currentPlayableMoment","currentPlayableSessionCut.moments"])->findOrFail($gid);$cm=$g->currentPlayableMoment;$cut=$g->currentPlayableSessionCut;$moments=[];if($cut){foreach($cut->moments()->orderBy("moment_order")->get() as $m){$c=is_array($m->scene_card)?$m->scene_card:[];$moments[]=["id"=>$m->id,"moment_key"=>$m->moment_key,"moment_order"=>$m->moment_order,"anchor_event_id"=>$m->anchor_event_id,"title"=>$c["title"]??null,"exit_condition"=>$c["exit_condition"]??null,"next_moment_key"=>$c["next_moment_key"]??null,"turn_budget"=>$c["turn_budget"]??null,"word_budget"=>$c["word_budget"]??null,"choice_ids"=>$c["choice_ids"]??null,"is_current"=>($m->id===$g->current_playable_moment_id)];}}$commits=[];$counts=[];$repairs=[];$failures=[];$files=[];foreach(glob(storage_path("logs/narration*.log"))?:[] as $file){$files[]=basename($file);$fh=fopen($file,"r");if(!$fh)continue;while(($line=fgets($fh))!==false){if(!str_contains($line,$gid))continue;if(!preg_match("/\\] (?:production|local|staging)\\.(INFO|WARNING|ERROR|DEBUG): ([\\w\\.]+) /",$line,$mm))continue;$type=$mm[2];$start=strpos($line,"{");$payload=null;if($start!==false){$decoded=json_decode(substr($line,$start),true);if(is_array($decoded))$payload=$decoded;}if(is_array($payload)&&isset($payload["game_id"])&&$payload["game_id"]!==$gid)continue;$counts[$type]=($counts[$type]??0)+1;$at=preg_match("/^\\[([^\\]]+)\\]/",$line,$tm)?$tm[1]:null;if($type==="boiler.moment_turn_committed"&&is_array($payload)){$sid=(string)($payload["source_prompt_id"]??"");if($sid!==""){$commits[$sid]=["logged_at"=>$at,"moment_key"=>$payload["moment_key"]??null,"exit_condition_met"=>$payload["exit_condition_met"]??null,"result"=>$payload["result"]??null,"next_moment_key"=>$payload["next_moment_key"]??null,"moment_footnote"=>$payload["moment_footnote"]??null,"anchor_event_id"=>$payload["anchor_event_id"]??null];}}$bad=str_contains($type,"repair")||str_contains($type,"defaulted")||str_contains($type,"demoted")||str_contains($type,"healed")||str_contains($type,"held")||str_contains($type,"stripped")||str_contains($type,"truncated")||str_contains($type,"mismatch");if($bad)$repairs[]=["at"=>$at,"event"=>$type,"payload"=>$payload];if(str_ends_with($type,"_failed"))$failures[]=["at"=>$at,"event"=>$type,"payload"=>$payload];}fclose($fh);}$turns=[];$joined=0;foreach($g->prompts()->orderBy("created_at")->get() as $i=>$p){$resp=strip_tags((string)$p->response);$sid=$p->source_prompt_id;$c=(is_string($sid)&&isset($commits[$sid]))?$commits[$sid]:null;if($c)$joined++;$turns[]=["n"=>$i+1,"id"=>$p->id,"at"=>(string)$p->created_at,"session"=>$p->session_number,"player"=>$p->prompt,"narrator"=>$resp,"narrator_words"=>str_word_count($resp),"choices"=>$p->choices,"source_prompt_id"=>$sid,"moment_key"=>$c["moment_key"]??null,"exit_condition_met"=>$c["exit_condition_met"]??null,"moment_result"=>$c["result"]??null,"next_moment_key"=>$c["next_moment_key"]??null,"moment_footnote"=>$c["moment_footnote"]??null,"log_at"=>$c["logged_at"]??null];}$pack=["exported_at"=>now()->toIso8601String(),"game"=>["id"=>$g->id,"user"=>$g->user->email,"story"=>$g->story->slug,"updated_at"=>(string)$g->updated_at,"session"=>$g->current_session_number,"complete"=>(bool)$g->current_session_complete,"cut_id"=>$g->current_playable_session_cut_id,"moment_id"=>$g->current_playable_moment_id,"moment_key"=>$cm?$cm->moment_key:null,"moment_order"=>$cm?$cm->moment_order:null,"playable_moment_turn_count"=>$g->playable_moment_turn_count,"footnote"=>$g->moment_footnote,"path"=>$g->defining_path_label,"choice_id"=>$g->defining_choice_id,"choice_line"=>$g->defining_choice_line,"world_state"=>$g->world_state,"alignment"=>$g->alignment_scaffold,"symbolic_memory"=>$g->symbolic_memory],"moments"=>$moments,"logs"=>["files_scanned"=>$files,"counts_by_type"=>$counts,"commits_joined_to_turns"=>$joined,"repair_count"=>count($repairs),"failure_count"=>count($failures),"repairs"=>$repairs,"failures"=>$failures],"turns"=>$turns];$json=json_encode($pack,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);if($json===false)throw new RuntimeException("json_encode failed");$path="exports/{$label}-{$gid}-evidence.json";Illuminate\Support\Facades\Storage::disk("public")->put($path,$json);echo "OK player={$player} slug={$slug} game_id={$gid}\n";echo "OK uploaded={$path}\n";echo "bytes=".strlen($json)." turns=".count($turns)." joined={$joined} repairs=".count($repairs)." failures=".count($failures)."\n";echo "moment=".($cm?$cm->moment_key:"null")." turn_count=".$g->playable_moment_turn_count."\n";}catch(Throwable $e){echo "ERROR ".$e::class.": ".$e->getMessage()."\n".$e->getFile().":".$e->getLine()."\n";}'
```

**Sanity check on stdout:**

| Field | Good | Bad |
|---|---|---|
| `turns` | Matches prompts played | 0 or much lower than session |
| `joined` | > 0 | 0 = log disk empty; moment columns unreliable in export |
| `repairs` | Note count; cite in analysis | High count = engine fighting itself |

Already have `game_id`? Use **Step B** in `laravel-cloud-commands.md` §6.1.

### 5 — Session 1 injected runtime prompt (what the model sees at open)

Generic S1 open (no live game):

```bash
php artisan tinker --execute='
$slug = "wasteland";
$session = 1;
$engine = app(App\Services\ChaosEngineService::class);
$story = App\Models\Story::where("slug", $slug)->firstOrFail();
$ctx = $engine->loadSessionContext($story, $session, null, null, null, null);
$rp = $engine->renderSystemPrompt($ctx, $engine->emptyWorldState(), $engine->emptyAlignmentScaffold(), null, true, false);
$dest = "exports/{$slug}-session-{$session}-runtime-injected.txt";
Illuminate\Support\Facades\Storage::disk("public")->put($dest, $rp);
echo "uploaded {$dest}: " . strlen($rp) . " bytes\n";
'
```

With a specific game's active moment card — set `$gid`.

### 6 — Narration trace (structured turn log from server logs)

If `game:trace` is available on the Cloud app:

```bash
php artisan game:trace PASTE_GAME_ULID --limit=50
```

Reads `storage/logs/narration*.log` for `narration.turn` rows. Use when evidence export has `joined=0` but you need moment/session assertions.

---

## Scoring & analysis

### Tester (during / after play)

1. Fill **Per-tester session sheet** in [BOILER-FUN-SCOREBOARD.md](./BOILER-FUN-SCOREBOARD.md).
2. Paste `game_id` and pillar quick scores (A–F).

### Analyst (from evidence JSON)

1. Copy [Boiler/QA/_ANALYSIS-TEMPLATE.md](./Boiler/QA/_ANALYSIS-TEMPLATE.md) → `docs/Boiler/QA/{slug}/{REVIEWER}-{slug}-SCORED-ANALYSIS.md`.
2. Score each row **0–5** with turn citations (`turn 12`, `moment_key S1_M04`).
3. Mark **not scorable** when export cannot see it (platform A1–A2, subjective B8/C7/D7/F*).
4. Add **Platform defects** section if code-level issues found (see PJS example).
5. Write **Headline** (3–5 sentences: what works, what fails, ship recommendation).

**Reference analysis:** [Boiler/Debug/Thomas log/PJS-THOMAS-SCORED-ANALYSIS.md](./Boiler/Debug/Thomas%20log/PJS-THOMAS-SCORED-ANALYSIS.md)

### Evidence JSON quick map

| Path | Use in analysis |
|---|---|
| `turns[].player` / `narrator` | Agency, voice, canon |
| `turns[].narrator_words` | D1 word budget |
| `turns[].moment_key` | E2 pacing (if `joined>0`) |
| `turns[].choices[].canonical_choice_id` | E1 authored branch % |
| `game.world_state` | B5, C3–C6 at export time |
| `game.footnote` | Intent the engine attributed |
| `moments[]` | Exit budgets vs actual turns |
| `logs.repairs` / `failures` | Platform + engine health |

**Handy counts** (local, after download):

```bash
# Turns with bound authored choices (example)
jq '[.turns[].choices[]? | select(.canonical_choice_id != null)] | length' evidence.json
jq '.turns | length' evidence.json
```

---

## Per-title rollup (weekly)

For each slug in [TITLE-TRACKER](./Boiler/QA/TITLE-TRACKER.md):

1. Collect all reviewer analyses for that title.
2. Average pillar scores per reviewer; note spread (>1 point = disagreement).
3. Update **Team totals** table in scoreboard.
4. List **blockers** (any A or B row at 0–1 with evidence).
5. **Ship / hold / retest** decision with owner.

---

## Launch Boiler stories (initial roster)

| Slug | Title | QA folder |
|---|---|---|
| `the-adventure-of-the-speckled-band` | Speckled Band | `docs/Boiler/QA/speckled-band/` |
| `wasteland` | Wasteland | `docs/Boiler/QA/wasteland/` |
| `pjs` | PJ's | `docs/Boiler/QA/pjs/` |
| `treasure-island` | Treasure Island | `docs/Boiler/QA/treasure-island/` |

Add a row to TITLE-TRACKER for every new Boiler adaptation before playtest begins.

---

## Checklist — one reviewer, one title

- [ ] `stories:boiler-status {slug}` green
- [ ] Played ≥15 turns, ≥5 freeform
- [ ] Session sheet filled (scoreboard)
- [ ] Evidence exported + saved under `docs/Boiler/QA/{slug}/`
- [ ] Scored analysis written from template
- [ ] TITLE-TRACKER row updated
- [ ] Blockers filed (GitHub / process log) if any pillar <2
