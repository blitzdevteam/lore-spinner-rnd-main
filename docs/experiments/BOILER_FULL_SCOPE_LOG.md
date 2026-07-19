# Boiler Pacing Engine — Implementation and Rollback Log

## Rollback anchors

- Baseline `main` SHA: `42d022984bdf77e6080f0fdb63de95ab19db160b`
- Git tag created before implementation: `pre-boiler`
- Database snapshot: **Daniel must take this before running Boiler migrations on Laravel Cloud**
- Implementation branch: `main`
- Push/deploy status: **not pushed by the agent**
- Runtime verification status: **implementation written, not runtime-verified; no migrations, tests, builds, Artisan commands, queues, or Cloud commands were executed by the agent**

## Nine revertable implementation stages

| Stage | Commit | Scope |
|---|---|---|
| 1 | `cf6cffc` | Schema, status enum, models, FKs, prompt idempotency column |
| 2 | `f2a9981` | Adaptation-time toggle and canonical session-chain wiring |
| 3 | `d88ea4a` | Story boiling guidance, shared event loading, Boiler Draft |
| 4 | `6a4b68c` | Phase 4-7 moment bindings |
| 5 | `9647f13` | Boiler Finalize, validation, normalization |
| 6 | `a6ab2e1` | Editorial audit and authoritative runtime assembly |
| 7 | `4f2b72c` | Runtime progression, atomic state commit, duplicate-turn protection, reset |
| 8 | `3d04a12` | Narration suggestions contract and frontend identity |
| 9 | `40e9780` | Inspection, exports, tests, and this runbook |

## Daniel-run pre-deployment checks

Run these from the Laravel Cloud Command UI. Do not deploy while adaptation jobs are active.

```bash
php artisan tinker --execute='echo "adaptation_jobs=" . DB::table("jobs")->where("queue", "adaptation")->count() . PHP_EOL;'
```

Expected before deployment: `adaptation_jobs=0`.

Take a complete database snapshot using Laravel Cloud's database backup facility before running migrations. Record its identifier here:

```text
PRE_BOILER_DATABASE_SNAPSHOT=
```

After an explicitly approved push/deploy:

```bash
php artisan migrate --force
php artisan queue:restart
```

## Daniel-run verification commands

Run against the testing database/environment, never the production database:

```bash
php artisan test --filter=Boiler
php artisan test
npm run lint
npm run build
```

The agent has not run these commands and does not claim they pass.

## Canary adaptation

Use one unpublished canary first:

```bash
php artisan stories:run-adaptation the-adventure-of-the-speckled-band --force --boiler
php artisan stories:boiler-status the-adventure-of-the-speckled-band
php artisan stories:inspect-boiler the-adventure-of-the-speckled-band --session=1
php artisan adaptation:export the-adventure-of-the-speckled-band
```

Verify:

1. Every session reaches `session_status=completed` only after Runtime Assembly.
2. Every Boiler session reaches `boiler_status=finalized`.
3. Every session has one `standard` cut and ordered moments.
4. The prompt contains `[BOILER_RUNTIME_V1]` and no full Section 12 script dump.
5. Opening narration reports false moment exit and no footnote.
6. Player turns store one response per `source_prompt_id`.
7. Zero-to-three suggestions render correctly; no unconditional Boiler Continue appears.
8. Final moment completion preserves cut/moment pointers until next session.

Previously adapted stories remain legacy until fully re-adapted with `--boiler`. Focused retrofit is intentionally deferred.

## Emergency operational stop

No live mode switch exists. Stop dispatching Boiler adaptations and do not start new re-adaptations. Existing Boiler games use their persisted adaptation until code/schema rollback and re-adaptation are completed.

## Full code rollback

The safe shared-history mechanism is `git revert`, not hard reset:

```bash
git log --oneline pre-boiler..HEAD
git revert 40e9780
git revert 3d04a12
git revert 4f2b72c
git revert a6ab2e1
git revert 9647f13
git revert 6a4b68c
git revert d88ea4a
git revert f2a9981
git revert cf6cffc
```

Push revert commits to `main` only with Daniel's approval and only while the adaptation queue is idle.

## Database rollback

The six Boiler migrations were created in FK-safe order. Roll them back in reverse order after code rollback:

```bash
php artisan migrate:rollback --step=6 --force
php artisan queue:restart
```

This drops prompt/game pointer columns before the referenced Boiler tables, then removes the additive adaptation columns. It does not modify canonical events, Phase 3-7 choice artifacts, or story source.

After rollback:

```bash
php artisan stories:run-adaptation <affected-story> --force
```

Re-adaptation is required so `runtime_narrator_prompt` returns to the pre-Boiler template. Reset test games and verify start, freeform, choices, consequences, next-session transition, completion, and reset.
