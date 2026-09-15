# Cloud export — Thomas Speckled Band evidence (one file → bucket)

Game: `01kt2mt8c2jvw7rpp6f2v3ptyp`

Paste the command below into **Laravel Cloud → Commands**.  
Output lands in File explorer as:

`exports/thomas-speckled-band-01kt2mt8c2jvw7rpp6f2v3ptyp-evidence.json`

Then download it into this folder.

---

```bash
php artisan tinker --execute='try{$gid="01kt2mt8c2jvw7rpp6f2v3ptyp";$g=App\Models\Game::with(["user","story","currentPlayableMoment","currentPlayableSessionCut.moments"])->findOrFail($gid);$cm=$g->currentPlayableMoment;$cut=$g->currentPlayableSessionCut;$moments=[];if($cut){foreach($cut->moments()->orderBy("moment_order")->get() as $m){$c=is_array($m->scene_card)?$m->scene_card:[];$moments[]=["id"=>$m->id,"moment_key"=>$m->moment_key,"moment_order"=>$m->moment_order,"anchor_event_id"=>$m->anchor_event_id,"title"=>$c["title"]??null,"exit_condition"=>$c["exit_condition"]??null,"next_moment_key"=>$c["next_moment_key"]??null,"turn_budget"=>$c["turn_budget"]??null,"word_budget"=>$c["word_budget"]??null,"choice_ids"=>$c["choice_ids"]??null,"is_current"=>($m->id===$g->current_playable_moment_id)];}}$commits=[];$counts=[];$repairs=[];$failures=[];$files=[];foreach(glob(storage_path("logs/narration*.log"))?:[] as $file){$files[]=basename($file);$fh=fopen($file,"r");if(!$fh)continue;while(($line=fgets($fh))!==false){if(!str_contains($line,$gid))continue;if(!preg_match("/\\] (?:production|local|staging)\\.(INFO|WARNING|ERROR|DEBUG): ([\\w\\.]+) /",$line,$mm))continue;$type=$mm[2];$start=strpos($line,"{");$payload=null;if($start!==false){$decoded=json_decode(substr($line,$start),true);if(is_array($decoded))$payload=$decoded;}if(is_array($payload)&&isset($payload["game_id"])&&$payload["game_id"]!==$gid)continue;$counts[$type]=($counts[$type]??0)+1;$at=preg_match("/^\\[([^\\]]+)\\]/",$line,$tm)?$tm[1]:null;if($type==="boiler.moment_turn_committed"&&is_array($payload)){$sid=(string)($payload["source_prompt_id"]??"");if($sid!==""){$commits[$sid]=["logged_at"=>$at,"moment_key"=>$payload["moment_key"]??null,"exit_condition_met"=>$payload["exit_condition_met"]??null,"result"=>$payload["result"]??null,"next_moment_key"=>$payload["next_moment_key"]??null,"moment_footnote"=>$payload["moment_footnote"]??null,"anchor_event_id"=>$payload["anchor_event_id"]??null];}}$bad=str_contains($type,"repair")||str_contains($type,"defaulted")||str_contains($type,"demoted")||str_contains($type,"healed")||str_contains($type,"held")||str_contains($type,"stripped")||str_contains($type,"truncated")||str_contains($type,"mismatch");if($bad)$repairs[]=["at"=>$at,"event"=>$type,"payload"=>$payload];if(str_ends_with($type,"_failed"))$failures[]=["at"=>$at,"event"=>$type,"payload"=>$payload];}fclose($fh);}$turns=[];$joined=0;foreach($g->prompts()->orderBy("created_at")->get() as $i=>$p){$resp=strip_tags((string)$p->response);$sid=$p->source_prompt_id;$c=(is_string($sid)&&isset($commits[$sid]))?$commits[$sid]:null;if($c)$joined++;$turns[]=["n"=>$i+1,"id"=>$p->id,"at"=>(string)$p->created_at,"session"=>$p->session_number,"player"=>$p->prompt,"narrator"=>$resp,"narrator_words"=>str_word_count($resp),"choices"=>$p->choices,"source_prompt_id"=>$sid,"moment_key"=>$c["moment_key"]??null,"exit_condition_met"=>$c["exit_condition_met"]??null,"moment_result"=>$c["result"]??null,"next_moment_key"=>$c["next_moment_key"]??null,"moment_footnote"=>$c["moment_footnote"]??null,"log_at"=>$c["logged_at"]??null];}$pack=["exported_at"=>now()->toIso8601String(),"game"=>["id"=>$g->id,"user"=>$g->user->email,"story"=>$g->story->slug,"updated_at"=>(string)$g->updated_at,"session"=>$g->current_session_number,"complete"=>(bool)$g->current_session_complete,"cut_id"=>$g->current_playable_session_cut_id,"moment_id"=>$g->current_playable_moment_id,"moment_key"=>$cm?$cm->moment_key:null,"moment_order"=>$cm?$cm->moment_order:null,"playable_moment_turn_count"=>$g->playable_moment_turn_count,"footnote"=>$g->moment_footnote,"path"=>$g->defining_path_label,"choice_id"=>$g->defining_choice_id,"choice_line"=>$g->defining_choice_line,"world_state"=>$g->world_state,"alignment"=>$g->alignment_scaffold,"symbolic_memory"=>$g->symbolic_memory],"moments"=>$moments,"logs"=>["files_scanned"=>$files,"counts_by_type"=>$counts,"commits_joined_to_turns"=>$joined,"repair_count"=>count($repairs),"failure_count"=>count($failures),"repairs"=>$repairs,"failures"=>$failures],"turns"=>$turns];$json=json_encode($pack,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);if($json===false)throw new RuntimeException("json_encode failed");$path="exports/thomas-speckled-band-{$gid}-evidence.json";Illuminate\Support\Facades\Storage::disk("public")->put($path,$json);echo "OK uploaded={$path}\n";echo "bytes=".strlen($json)." turns=".count($turns)." joined={$joined} repairs=".count($repairs)." failures=".count($failures)."\n";echo "moment=".($cm?$cm->moment_key:"null")." turn_count=".$g->playable_moment_turn_count."\n";}catch(Throwable $e){echo "ERROR ".$e::class.": ".$e->getMessage()."\n".$e->getFile().":".$e->getLine()."\n";}'
```

---

### Expected stdout

```text
OK uploaded=exports/thomas-speckled-band-01kt2mt8c2jvw7rpp6f2v3ptyp-evidence.json
bytes=... turns=65 joined=... repairs=... failures=...
moment=S2_M07 turn_count=10
```

- `joined>0` → each turn has `moment_key` / `moment_footnote` from narration logs  
- `joined=0` → Cloud log disk is empty/rotated; still get full transcript + `game.footnote` + `moments[]` with exit budgets  

If you see `ERROR ...` paste that stdout back.  
If stdout is blank again, Cloud mangled the paste — say so and we’ll split into two shorter commands.
