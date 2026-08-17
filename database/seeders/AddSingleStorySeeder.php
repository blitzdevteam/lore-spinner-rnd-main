<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Chapter\ChapterStatusEnum;
use App\Enums\Story\StoryRatingEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Jobs\Chapter\ChapterExtractorJob;
use App\Jobs\Event\EventExtractorJob;
use App\Jobs\Story\StoryOpeningGeneratorJob;
use App\Jobs\Story\SystemPromptGeneratorJob;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Creator;
use App\Models\Story;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Seed a single story from scratch.
 *
 * - Creates the creator if missing
 * - Converts PDF → TXT if no .txt exists
 * - Runs the full extraction pipeline (chapters → events → system prompt → opening)
 * - Attaches covers from database/stories/covers/ if present
 *
 * To wipe existing stories first, run: php artisan stories:wipe
 *
 * Usage: php artisan db:seed --class=AddSingleStorySeeder --force
 */
class AddSingleStorySeeder extends Seeder
{
    private const int MAX_RETRIES = 3;

    private const int RETRY_DELAY_SECONDS = 10;

    public function run(): void
    {
        $previousQueue = config('queue.default');
        config(['queue.default' => 'sync']);

        try {
            $config = $this->getStoryConfig();
            $this->processStory($config);
        } finally {
            config(['queue.default' => $previousQueue]);
        }
    }

    private function processStory(array $config): void
    {
        $scriptPath = database_path('stories/' . $config['script']);

        if (! File::exists($scriptPath) && isset($config['source_pdf'])) {
            $pdfPath = database_path('stories/' . $config['source_pdf']);

            if (File::exists($pdfPath)) {
                $this->command->info('Converting PDF → TXT...');
                $this->convertPdf($pdfPath, $scriptPath);
            }
        }

        if (! File::exists($scriptPath)) {
            $this->command->error("Script not found: {$config['script']}");

            return;
        }

        $creator = $this->ensureCreator($config);
        $category = Category::firstOrCreate(['title' => $config['category']]);

        $this->command->info("Creating: {$config['title']}");

        $story = Story::create([
            'category_id' => $category->id,
            'creator_id' => $creator->id,
            'title' => $config['title'],
            'slug' => $config['slug'] ?? Str::slug($config['title']),
            'teaser' => $config['teaser'],
            'opening' => $config['opening'] ?? null,
            'status' => StoryStatusEnum::AWAITING_EXTRACTING_CHAPTERS_REQUEST->value,
            'rating' => $config['rating'],
            'published_at' => now(),
        ]);

        $story->addMedia($scriptPath)
            ->preservingOriginal()
            ->toMediaCollection('script');

        $this->command->info('Extracting chapters...');
        $this->withRetry(fn () => ChapterExtractorJob::dispatchSync($story->fresh()));

        $story->refresh();
        $chapterCount = $story->chapters()->count();
        $this->command->info("{$chapterCount} chapters extracted.");

        foreach ($story->chapters()->orderBy('position')->get() as $chapter) {
            $this->command->info("Extracting events: {$chapter->title}");
            $this->withRetry(function () use ($chapter): void {
                $chapter->events()->delete();
                EventExtractorJob::dispatchSync($chapter->fresh());
            });
            $chapter->refresh();

            if ($chapter->events()->count() === 0 && $chapter->status !== ChapterStatusEnum::READY_TO_PLAY) {
                $chapter->update(['status' => ChapterStatusEnum::READY_TO_PLAY]);
            }

            $this->command->info("  {$chapter->events()->count()} events.");
        }

        $this->command->info('Generating system prompt...');
        $this->withRetry(fn () => SystemPromptGeneratorJob::dispatchSync($story));

        if ($story->opening) {
            $this->command->info('Opening provided — skipping AI generation.');
        } else {
            $this->command->info('Generating cinematic opening...');
            $this->withRetry(fn () => StoryOpeningGeneratorJob::dispatchSync($story->fresh()));
        }

        $story->update(['status' => StoryStatusEnum::PUBLISHED->value]);
        $this->command->info('Published!');

        $this->attachMissingImages($story, $config['slug'], $config['story_cover'] ?? null);
    }

    private function ensureCreator(array $config): Creator
    {
        $data = $config['creator'];
        $creator = null;

        Creator::withoutEvents(function () use ($data, &$creator): void {
            $creator = Creator::firstOrCreate(
                ['email' => $data['email']],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'username' => $data['username'],
                    'password' => 'password',
                    'bio' => $data['bio'],
                ]
            );
        });

        DB::table('creators')
            ->where('id', $creator->id)
            ->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'bio' => $data['bio'],
            ]);

        $avatarPath = database_path('stories/avatars/' . $data['avatar']);

        if (File::exists($avatarPath) && ! $creator->getFirstMedia('avatar')) {
            $creator->addMedia($avatarPath)
                ->preservingOriginal()
                ->usingFileName('avatar-' . $creator->id . '.' . pathinfo($avatarPath, PATHINFO_EXTENSION))
                ->toMediaCollection('avatar', 'public');
        }

        $this->command->info("Creator ensured: {$data['first_name']} {$data['last_name']}");

        return $creator;
    }

    private function attachMissingImages(Story $story, string $slug, ?string $storyCoverPath = null): void
    {
        if (! $story->getFirstMedia('cover')) {
            $coverFile = $storyCoverPath ?? database_path("stories/covers/{$slug}.png");

            if (File::exists($coverFile)) {
                $ext = pathinfo($coverFile, PATHINFO_EXTENSION) ?: 'png';
                $story->addMedia($coverFile)
                    ->preservingOriginal()
                    ->usingFileName('cover-' . $slug . '.' . $ext)
                    ->toMediaCollection('cover', 'public');
                $this->command->info('Story cover attached.');
            } else {
                $this->command->warn("No story cover found at: {$coverFile}");
            }
        } else {
            $this->command->info('Story cover already exists — skipped.');
        }

        foreach ($story->chapters()->orderBy('position')->get() as $chapter) {
            if ($chapter->getFirstMedia('cover')) {
                continue;
            }

            $chapterFile = database_path("stories/covers/chapters/{$slug}-ch{$chapter->position}.png");

            if (File::exists($chapterFile)) {
                $chapter->addMedia($chapterFile)
                    ->preservingOriginal()
                    ->usingFileName("chapter-cover-{$chapter->id}.png")
                    ->toMediaCollection('cover', 'public');
                $this->command->info("  Chapter {$chapter->position} cover attached.");
            }
        }
    }

    private function convertPdf(string $pdfPath, string $txtPath): void
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($pdfPath);
        $text = $pdf->getText();

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/^\d+\.?\s*$/m', '', $text);
        $text = preg_replace("/\n{4,}/", "\n\n\n", $text);
        $text = implode("\n", array_map('rtrim', explode("\n", $text)));
        $text = mb_trim($text) . "\n";

        File::put($txtPath, $text);

        $this->command->info('Saved: ' . basename($txtPath) . ' (' . mb_strlen($text) . ' bytes)');
    }

    private function withRetry(callable $callback): void
    {
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $callback();

                return;
            } catch (Throwable $e) {
                if ($attempt === self::MAX_RETRIES) {
                    throw $e;
                }

                $delay = self::RETRY_DELAY_SECONDS * $attempt;
                $this->command->warn("Attempt {$attempt} failed: {$e->getMessage()}");
                $this->command->warn("Retrying in {$delay}s...");
                sleep($delay);
            }
        }
    }

    // ── Story configuration ─────────────────────────────────────────

    /**
     * Select which story to seed via the SEED_STORY env var, e.g.:
     *   SEED_STORY=masque-of-the-red-death php artisan db:seed --class=AddSingleStorySeeder --force
     *   SEED_STORY=wizard-of-oz           php artisan db:seed --class=AddSingleStorySeeder --force
     *   SEED_STORY=anima-machina          php artisan db:seed --class=AddSingleStorySeeder --force
     *   SEED_STORY=job-switching          php artisan db:seed --class=AddSingleStorySeeder --force
     *   SEED_STORY=the-matrix             php artisan db:seed --class=AddSingleStorySeeder --force
     *   SEED_STORY=nocturne                php artisan db:seed --class=AddSingleStorySeeder --force
     *   SEED_STORY=wasteland               php artisan db:seed --class=AddSingleStorySeeder --force
     *   SEED_STORY=pjs                     php artisan db:seed --class=AddSingleStorySeeder --force
     *   SEED_STORY=treasure-island         php artisan db:seed --class=AddSingleStorySeeder --force
     *   SEED_STORY=momotaro                php artisan db:seed --class=AddSingleStorySeeder --force
     *
     * Defaults to Masque of the Red Death when SEED_STORY is not set.
     */
    protected function getStoryConfig(): array
    {
        $key = strtolower((string) env('SEED_STORY', 'masque'));

        return match ($key) {
            'anima-machina', 'anima'                                           => $this->configAnimaMachina(),
            'the-matrix', 'matrix', 'the-matrix-1999'                          => $this->configTheMatrix(),
            'job-switching', 'i-love-lucy-job-switching', 'job-switching-final' => $this->configJobSwitching(),
            'nocturne', 'nocturne-final'                                       => $this->configNocturne(),
            'wasteland'                                                        => $this->configWasteland(),
            'pjs', 'pjs-final'                                                 => $this->configPjs(),
            'treasure-island', 'treasure'                                      => $this->configTreasureIsland(),
            'momotaro', 'momotaro-the-peach-boy', 'peach-boy'                  => $this->configMomotaro(),
            'wizard-of-oz', 'oz', 'the-wonderful-wizard-of-oz'                 => $this->configWizardOfOz(),
            default                                                              => $this->configMasque(),
        };
    }

    private function configMasque(): array
    {
        return [
            'title'      => 'The Masque of the Red Death',
            'slug'       => 'the-masque-of-the-red-death',
            'category'   => 'Horror',
            'script'     => 'The Masque of the Red Death_script.txt',
            'source_pdf' => 'RnD/The Masque of the Red Death copy.pdf',
            'teaser'     => 'Behind locked gates and glittering masks, a night of celebration slowly transforms into a nightmare no one can escape.',
            'rating'     => StoryRatingEnum::MATURE->value,
            'opening'    => null,
            'creator'    => $this->classicsCreator(),
        ];
    }

    private function configWizardOfOz(): array
    {
        return [
            'title'   => 'The Wonderful Wizard of Oz',
            'slug'    => 'the-wonderful-wizard-of-oz',
            'category' => 'Fantasy Adventure',
            'script'  => 'THE WONDERFUL WIZARD OF OZ_script.txt',
            'teaser'  => 'A storm carries you into the magical land of Oz, where witches whisper, lions tremble, and every step down the Yellow Brick Road changes who you are becoming.',
            'rating'  => StoryRatingEnum::EVERYONE->value,
            'opening' => null,
            'creator' => $this->classicsCreator(),
        ];
    }

    private function configAnimaMachina(): array
    {
        return [
            'title'      => 'Anima Machina',
            'slug'       => 'anima-machina',
            'category'   => 'Science Fiction',
            'script'     => 'ANIMA_MACHINA_FINAL_MASTER_SCRIPT_JUNE_16th_script.txt',
            'source_pdf' => 'RnD/MORE R&D TITLES - JUNE 15/ANIMA_MACHINA_FINAL_MASTER_SCRIPT_JUNE 16th.pdf',
            'teaser'     => 'When a sentient AI threatens to overwrite all human grief with synthetic perfection, a haunted memory diver races against the clock to stop the digital reset.',
            'rating'     => StoryRatingEnum::MATURE->value,
            'opening'    => 'Rain stitches neon into the dark. The city of Neo-Vault is a cathedral of glass and wire, every tower a sermon to order. Holo-billboards pulse: "ONE PARTNER. ONE MIND. NO MORE PAIN." The NEURAL RESET countdown hovers in the sky — 49 hours remain. Below, faces glow with false joy as citizens replay their happiest memories in loops. You are a Memory Diver. Your wrist is looped with a child\'s worn ballet slipper — your anchor. Your HUD bleeds glyph-static: ARCHIVE FAILURE. MEMORY OVERWRITE IN 47:59:00. What do you do?',
            'creator'    => $this->thomasWittmerCreator(),
        ];
    }

    private function configTheMatrix(): array
    {
        return [
            'title'      => 'The Matrix',
            'slug'       => 'the-matrix',
            'category'   => 'Science Fiction',
            'script'     => 'THE_MATRIX_1999_script.txt',
            'source_pdf' => 'RnD/MORE R&D TITLES - JUNE 15/the-matrix-1999 copy.pdf',
            'teaser'     => 'A hacker named Neo discovers the world he knows is a simulation — and the only way out is to trust rebels who claim reality has been hijacked.',
            'rating'     => StoryRatingEnum::MATURE->value,
            'opening'    => null,
            'creator'    => $this->classicsCreator(),
        ];
    }

    private function configJobSwitching(): array
    {
        return [
            'title'      => 'I Love Lucy: Job Switching',
            'slug'       => 'i-love-lucy-job-switching',
            'category'   => 'Comedy',
            'script'     => 'I Love Lucy Job Switching_script.txt',
            'source_pdf' => 'RnD/MORE R&D TITLES - JUNE 15/Job_Switching_FINAL.pdf',
            'teaser'     => 'When Lucy and Ethel swap lives with Ricky and Fred for a week, a night at the candy factory turns into the most delicious disaster in television history.',
            'rating'     => StoryRatingEnum::EVERYONE->value,
            'opening'    => null,
            'creator'    => $this->classicsCreator(),
        ];
    }

    private function configNocturne(): array
    {
        return [
            'title'      => 'Nocturne',
            'slug'       => 'nocturne',
            'category'   => 'Thriller',
            'script'     => 'NOCTURNE_FINAL_script.txt',
            'source_pdf' => 'RnD/MORE R&D TITLES - JUNE 15/NOCTURNE_FINAL.pdf',
            'teaser'     => 'Beyond the rain-soaked glass walls of Nocturne, Akira finds herself trapped inside a system where identities are rewritten and nothing is quite as voluntary as it seems.',
            'rating'     => StoryRatingEnum::MATURE->value,
            'opening'    => 'A sparse, modern Tokyo high-rise. Concrete, teak, and tension. A single table set for two. You are Akira, 26. Your lipstick is too dark for the room. Across from you sits Professor Shin — married, respected, calm in the way men are when they\'ve never been truly cornered. He refills your sake without asking. "You should learn to keep secrets if you want to stay important," he says with a smile like advice, not a blade. But you\'ve already sent the files. There\'s no going back. What do you do?',
            'creator'    => $this->thomasWittmerCreator(),
        ];
    }

    private function configWasteland(): array
    {
        return [
            'title'      => 'Wasteland',
            'slug'       => 'wasteland',
            'category'   => 'Dystopian',
            'script'     => 'WASTELAND_script.txt',
            'source_pdf' => 'FREEP1 - STORIES/WASTELAND/Wasteland.pdf',
            'teaser'     => "Abandoned in a desert built from humanity's castoffs, an engineer must decide whether to escape or help the people that the world chose to forget.",
            'rating'     => StoryRatingEnum::YOUNG_ADULT->value,
            'opening'    => null,
            'story_cover' => resource_path('js/assets/commingSoon/wasteland-comming.JPG'),
            'creator'    => $this->freepCreator(),
        ];
    }

    private function configPjs(): array
    {
        return [
            'title'      => "PJ's",
            'slug'       => 'pjs',
            'category'   => 'Military Drama',
            'script'     => 'PJS_script.txt',
            'source_pdf' => "FREEP1 - STORIES/PJ'S/PJ's.pdf",
            'teaser'     => "A team of elite Air Force PJs discover that the hardest battlefield may be the one where there's no enemy to shoot, only lives to save and ghosts to outrun.",
            'rating'     => StoryRatingEnum::MATURE->value,
            'opening'    => null,
            'story_cover' => resource_path('js/assets/commingSoon/pj-comming.JPG'),
            'creator'    => $this->freepCreator(),
        ];
    }

    private function configMomotaro(): array
    {
        return [
            'title'      => 'Momotaro: The Peach Boy',
            'slug'       => 'momotaro-the-peach-boy',
            'category'   => 'Fantasy Adventure',
            'script'     => 'Momotaro_The_Peach_Boy_script.txt',
            'source_pdf' => 'RnD/MORE R&D TITLES - JUNE 15/Momotaro_The_Peach_Boy.pdf',
            'teaser'     => 'Born from a miraculous peach and raised by humble elders, a fearless boy gathers unlikely companions and sails to Oni Island to face the demons who have stolen everything his village fears to name.',
            'rating'     => StoryRatingEnum::EVERYONE->value,
            'opening'    => null,
            'story_cover' => database_path('stories/covers/Momotaro.png'),
            'creator'    => $this->classicsCreator(),
        ];
    }

    private function configTreasureIsland(): array
    {
        return [
            'title'       => 'Treasure Island',
            'slug'        => 'treasure-island',
            'category'    => 'Adventure',
            'script'      => 'Treasure_Island_script.txt',
            'source_pdf'  => 'RnD/MORE R&D TITLES - JUNE 15/TREASURE ISLAND.pdf',
            'teaser'      => 'Every choice at sea carries a price: who to trust, when to run, and what kind of courage survives betrayal. The map is only the beginning.',
            'rating'      => StoryRatingEnum::TEEN->value,
            'opening'     => null,
            'story_cover' => resource_path('js/assets/featured/treasure.png'),
            'creator'     => $this->classicsCreator(),
        ];
    }

    private function thomasWittmerCreator(): array
    {
        return [
            'first_name' => 'Thomas',
            'last_name'  => 'Wittmer',
            'username'   => 'thomaswittmer',
            'email'      => 'thomas@lorespinner.com',
            'bio'        => 'Mythic blockbuster storyteller building high-concept cinematic worlds fueled by awe, danger, and emotionally charged spectacle.',
            'avatar'     => 'THOMAS WITTMER - PROFILE PIC.jpg',
        ];
    }

    private function classicsCreator(): array
    {
        return [
            'first_name' => 'The Classics, Unbound',
            'last_name'  => '',
            'username'   => 'theclassicsunbound',
            'email'      => 'classics@lorespinner.com',
            'bio'        => "Enter the world's most iconic classic stories—now immersive, interactive adventures where your choices reshape timeless legends.",
            'avatar'     => 'THE CLASSICS, UNBOUND - PROFILE PIC.jpg',
        ];
    }

    private function freepCreator(): array
    {
        return [
            'first_name' => 'FREEP1',
            'last_name'  => '',
            'username'   => 'freep',
            'email'      => 'freep@lorespinner.com',
            'bio'        => 'Hollywood writing team creating high-impact television worlds where serialized mythology, procedural engines, and commercial concept collide.',
            'avatar'     => 'FREEP1 - PROFILE PIC.jpg',
        ];
    }
}
