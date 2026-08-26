# Adding a New Story — Runbook

Documented from the Sherlock Holmes onboarding (May 2026).  
All commands are in the exact order they must be run.

---

## Prerequisites

- Place the source PDF under `database/stories/RnD/` **or** the launch library at `database/stories/launch/{world}/{slug}/{Title}.pdf` (see [launch/README.md](../database/stories/launch/README.md))
- Have Laravel Cloud CLI access ready

---

## Command 1 — Convert PDF → TXT (LOCAL)

Run this on your local machine:

```bash
php artisan tinker --execute="
\$parser = new \Smalot\PdfParser\Parser;
\$pdf = \$parser->parseFile(database_path('stories/RnD/YOUR FILE.pdf'));
\$text = \$pdf->getText();
\$text = str_replace([\"\\r\\n\", \"\\r\"], \"\\n\", \$text);
\$text = preg_replace('/^\d+\.?\s*$/m', '', \$text);
\$text = preg_replace(\"/\\n{4,}/\", \"\\n\\n\\n\", \$text);
\$text = implode(\"\\n\", array_map('rtrim', explode(\"\\n\", \$text)));
\$text = mb_trim(\$text) . \"\\n\";
\$out = database_path('stories/YOUR FILE_script.txt');
\Illuminate\Support\Facades\File::put(\$out, \$text);
echo 'Done: ' . basename(\$out) . ' (' . mb_strlen(\$text) . ' bytes)' . PHP_EOL;
"
```

The output TXT must be named `<TITLE>_script.txt` and saved directly under `database/stories/` (not in a subfolder).

---

## Command 2 — Commit & Push TXT (LOCAL)

```bash
git add "database/stories/YOUR FILE_script.txt"
git commit -m "Add <Title> script TXT (converted from PDF locally)"
git push
```

---

## Command 3 — Update Seeder Config (LOCAL)

Edit `database/seeders/AddSingleStorySeeder.php` → `getStoryConfig()`:

```php
return [
    'title'      => 'Your Story Title',
    'slug'       => 'your-story-title',           // Str::slug of title
    'category'   => 'Mystery & Detective',         // must match or create a Category
    'script'     => 'YOUR FILE_script.txt',        // relative to database/stories/
    'source_pdf' => 'RnD/YOUR FILE.pdf',           // fallback only — TXT should already exist
    'teaser'     => 'One-sentence hook shown on the story card.',
    'rating'     => StoryRatingEnum::TEEN->value,  // EVERYONE | TEEN | MATURE
    'opening'    => null,                          // null = AI generates it; or pass HTML string
    'creator'    => [
        'first_name' => 'The Classics, Unbound',
        'last_name'  => '',
        'username'   => 'theclassicsunbound',
        'email'      => 'classics@lorespinner.com',
        'bio'        => "Enter the world's most iconic classic stories...",
        'avatar'     => 'THE CLASSICS, UNBOUND - PROFILE PIC.jpg',
    ],
];
```

Then commit and push:

```bash
git add database/seeders/AddSingleStorySeeder.php
git commit -m "Configure AddSingleStorySeeder for <Title>"
git push
```

---

## Command 4 — Run Extraction Pipeline (LARAVEL CLOUD CLI)

```bash
php artisan db:seed --class="Database\Seeders\AddSingleStorySeeder" --force
```

Runs fully synchronously. Watch the output for each step:

```
Converting PDF → TXT...       ← skipped if TXT already exists
Extracting chapters...
X chapters extracted.
Extracting events: <Chapter Title>
  X events.
...
Generating system prompt...
Generating cinematic opening...
Published!
```

Expect 5–20 minutes depending on chapter/event count. Wait for `Published!` before continuing.

---

## Command 5 — Run Adaptation Pipeline (LARAVEL CLOUD CLI)

```bash
php artisan stories:run-adaptation your-story-slug
```

Dispatches `RunAdaptationPipelineJob` to the `adaptation` queue. The chain runs async:

```
FormatDetection → IpAudit → StorySessionMap
  └─ per session: EntryPointDiagnosis → SessionArchitecture → ChoiceDesign
                  → ConsequenceMapping → SessionClose → EditorialVerification
AdaptationStatusReconciliationJob
```

To re-run from scratch (wipes existing adaptation and restarts):

```bash
php artisan stories:run-adaptation your-story-slug --force
```

---

## Command 6 — Check Adaptation Progress (LARAVEL CLOUD CLI)

```bash
php artisan tinker --execute="
\$s = App\Models\Story::where('slug','your-story-slug')->firstOrFail();
\$a = \$s->adaptation()->with('sessionAdaptations')->firstOrFail();
echo json_encode([
  'adaptation_status' => \$a->adaptation_status->value,
  'story_wide' => [
    'format_detection'  => \$a->format_detection,
    'ip_audit'          => \$a->ip_audit,
    'story_session_map' => \$a->story_session_map,
  ],
  'sessions' => \$a->sessionAdaptations->sortBy('session_number')->map(fn(\$s)=>[
    'session_number'         => \$s->session_number,
    'session_status'         => \$s->session_status->value,
    'entry_point_diagnosis'  => \$s->entry_point_diagnosis,
    'session_architecture'   => \$s->session_architecture,
    'session_choice_design'  => \$s->session_choice_design,
    'choice_consequence_map' => \$s->choice_consequence_map,
    'session_close_design'   => \$s->session_close_design,
    'editorial_verification' => \$s->editorial_verification,
  ])->values(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
"
```

---

## Command 7 — Dispatch Image Generation Jobs (LARAVEL CLOUD CLI)

```bash
php artisan tinker --execute="
\$story = App\Models\Story::where('slug','your-story-slug')->firstOrFail();
dispatch(new App\Jobs\Story\StoryCoverGeneratorJob(\$story))->delay(now());
dispatch(new App\Jobs\Story\StoryBannerGeneratorJob(\$story))->delay(now()->addSeconds(15));
\$story->chapters()->orderBy('position')->each(function(\$ch, \$i) {
  dispatch(new App\Jobs\Chapter\ChapterCoverGeneratorJob(\$ch))->delay(now()->addSeconds(30 + \$i * 15));
});
echo 'All image jobs dispatched for: ' . \$story->title . PHP_EOL;
"
```

---

## Command 8 — Process the Image Queue (LARAVEL CLOUD CLI)

Image jobs sit on the `image-generation` queue. Run the worker to process them:

```bash
php artisan queue:work --queue=image-generation --stop-when-empty --tries=3
```

Stops automatically when the queue is empty. Each job takes ~10–30s so expect 2–4 minutes for a full story.

---

## Command 9 — Verify Images Attached (LARAVEL CLOUD CLI)

```bash
php artisan tinker --execute="
\$story = App\Models\Story::where('slug','your-story-slug')->with('media','chapters.media')->firstOrFail();
echo 'STORY: ' . \$story->title . PHP_EOL;
echo '  cover:  ' . (\$story->getFirstMediaUrl('cover')  ?: 'MISSING') . PHP_EOL;
echo '  banner: ' . (\$story->getFirstMediaUrl('banner') ?: 'MISSING') . PHP_EOL;
echo PHP_EOL . 'CHAPTERS:' . PHP_EOL;
foreach (\$story->chapters()->orderBy('position')->get() as \$ch) {
  echo '  Ch' . \$ch->position . ' ' . \$ch->title . ': ' . (\$ch->getFirstMediaUrl('cover') ?: 'MISSING') . PHP_EOL;
}
"
```

If anything shows `MISSING`, check for failed jobs:

```bash
php artisan tinker --execute="
\$failed = DB::table('failed_jobs')->orderByDesc('failed_at')->get();
echo \$failed->count() . ' total failed jobs' . PHP_EOL . PHP_EOL;
foreach(\$failed->take(10) as \$f) {
  \$p = json_decode(\$f->payload, true);
  echo '[' . \$f->failed_at . '] ' . (\$p['displayName'] ?? '?') . PHP_EOL;
  echo Str::limit(\$f->exception, 300) . PHP_EOL . PHP_EOL;
}
"
```

---

## Wipe and Restart

To delete all stories and start over:

```bash
php artisan stories:wipe --force
```

There is no per-story wipe — this clears everything.

---

## Key Files

| File | Purpose |
|------|---------|
| `database/stories/launch/manifest.php` | Launch title registry (17 titles, 6 worlds + standalone) |
| `database/stories/launch/README.md` | Launch folder layout and batch workflow |
| `database/seeders/AddSingleStorySeeder.php` | Single-story config + runs extraction pipeline (`SEED_STORY` from manifest) |
| `app/Console/Commands/RunAdaptationCommand.php` | `stories:run-adaptation` command |
| `app/Console/Commands/GenerateMissingImagesCommand.php` | `images:generate-missing` command |
| `app/Jobs/Adaptation/RunAdaptationPipelineJob.php` | Adaptation pipeline entry point |
| `database/stories/<TITLE>_script.txt` | Converted plaintext script (committed to git) |
