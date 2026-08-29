<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ComingSoonStory\ComingSoonStorySectionEnum;
use App\Models\ComingSoonStory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class SeedComingSoonStoriesCommand extends Command
{
    protected $signature = 'stories:seed-coming-soon
                            {--replace-covers : Re-attach cover images even when a cover already exists}';

    protected $description = 'Seed coming soon stories from the previous hard-coded home sections';

    public function handle(): int
    {
        $createdCount = 0;
        $updatedCount = 0;
        $attachedCount = 0;
        $missingCoverCount = 0;

        foreach ($this->presets() as $preset) {
            $lookup = [
                'section' => $preset['section'],
                'slug' => $preset['slug'],
            ];

            $record = ComingSoonStory::query()->updateOrCreate($lookup, [
                'title' => $preset['title'],
                'teaser' => $preset['teaser'],
                'category' => $preset['category'],
                'rating' => $preset['rating'],
                'author' => $preset['author'],
                'series_label' => $preset['series_label'],
                'image_position' => $preset['image_position'],
                'position' => $preset['position'],
                'is_active' => true,
            ]);

            if ($record->wasRecentlyCreated) {
                $createdCount++;
            } else {
                $updatedCount++;
            }

            $coverPath = base_path($preset['cover_path']);

            if (! File::exists($coverPath)) {
                $missingCoverCount++;
                $this->warn("Missing cover for [{$record->title}] at [{$preset['cover_path']}]");

                continue;
            }

            if (! $this->option('replace-covers') && $record->getFirstMedia('cover')) {
                continue;
            }

            $record->clearMediaCollection('cover');

            $record
                ->addMedia($coverPath)
                ->preservingOriginal()
                ->usingFileName($this->buildCoverFileName($record, $coverPath))
                ->toMediaCollection('cover', 'public');

            $attachedCount++;
        }

        $this->newLine();
        $this->info("Created: {$createdCount}");
        $this->info("Updated: {$updatedCount}");
        $this->info("Covers attached: {$attachedCount}");

        if ($missingCoverCount > 0) {
            $this->warn("Missing covers: {$missingCoverCount}");
        }

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{
     *   section: string,
     *   title: string,
     *   slug: string,
     *   teaser: string,
     *   category: string|null,
     *   rating: string|null,
     *   author: string|null,
     *   series_label: string|null,
     *   image_position: string|null,
     *   position: int,
     *   cover_path: string
     * }>
     */
    private function presets(): array
    {
        return [
            [
                'section' => ComingSoonStorySectionEnum::HERO->value,
                'title' => 'The Tell-Tale Heart',
                'slug' => 'the-tell-tale-heart',
                'teaser' => "As guilt begins to twist reality around him, a man struggles to silence the terrifying sound he cannot escape: the beating of a dead man's heart.",
                'category' => null,
                'rating' => null,
                'author' => 'Edgar Allan Poe',
                'series_label' => 'A LoreSpinner Classic Adaptation',
                'image_position' => 'object-[62%_top] md:object-top',
                'position' => 1,
                'cover_path' => 'resources/js/assets/carosel/Ultrawide the tell tale heart.png',
            ],
            [
                'section' => ComingSoonStorySectionEnum::HERO->value,
                'title' => 'The Masque of the Red Death',
                'slug' => 'the-masque-of-the-red-death',
                'teaser' => 'Behind locked gates and glittering masks, a night of celebration slowly transforms into a nightmare no one can escape.',
                'category' => null,
                'rating' => null,
                'author' => 'Edgar Allan Poe',
                'series_label' => 'A LoreSpinner Classic Adaptation',
                'image_position' => 'object-[68%_top] md:object-top',
                'position' => 2,
                'cover_path' => 'resources/js/assets/carosel/Ultrawide 21_9 The Masque of the Red Death.png',
            ],
            [
                'section' => ComingSoonStorySectionEnum::HERO->value,
                'title' => 'Nocturne',
                'slug' => 'nocturne',
                'teaser' => 'Beyond the rain-soaked glass walls of Nocturne, Akira finds herself trapped inside a system where identities are rewritten and nothing is quite as voluntary as it seems.',
                'category' => null,
                'rating' => null,
                'author' => 'Thomas Wittmer',
                'series_label' => 'A LoreSpinner Original',
                'image_position' => 'object-[70%_top] md:object-top',
                'position' => 3,
                'cover_path' => 'resources/js/assets/carosel/Ultrawide nocturne.png',
            ],
            [
                'section' => ComingSoonStorySectionEnum::HERO->value,
                'title' => 'Jane Eyre',
                'slug' => 'jane-eyre',
                'teaser' => 'A young orphan enters a dark and mysterious estate where buried secrets, dangerous love, and the search for belonging may change the course of her life forever.',
                'category' => null,
                'rating' => null,
                'author' => 'Charlotte Brontë',
                'series_label' => 'A LoreSpinner Classic Adaptation',
                'image_position' => 'object-[72%_top] md:object-top',
                'position' => 4,
                'cover_path' => 'resources/js/assets/carosel/Ultrawide jane eyre.png',
            ],
            [
                'section' => ComingSoonStorySectionEnum::FEATURED_WORLDS->value,
                'title' => 'Nocturne',
                'slug' => 'nocturne',
                'teaser' => 'Beyond the rain-soaked glass walls of Nocturne, Akira finds herself trapped inside a system where identities are rewritten and nothing is quite as voluntary as it seems.',
                'category' => null,
                'rating' => null,
                'author' => null,
                'series_label' => null,
                'image_position' => null,
                'position' => 1,
                'cover_path' => 'resources/js/assets/featured/nocturne.png',
            ],
            [
                'section' => ComingSoonStorySectionEnum::FEATURED_WORLDS->value,
                'title' => 'The Masque of the Red Death',
                'slug' => 'the-masque-of-the-red-death',
                'teaser' => 'Behind locked gates and glittering masks, a night of celebration slowly transforms into a nightmare no one can escape.',
                'category' => null,
                'rating' => null,
                'author' => null,
                'series_label' => null,
                'image_position' => null,
                'position' => 2,
                'cover_path' => 'resources/js/assets/featured/redDeath.png',
            ],
            [
                'section' => ComingSoonStorySectionEnum::FEATURED_WORLDS->value,
                'title' => 'Anima Machina',
                'slug' => 'anima-machina',
                'teaser' => 'When a sentient AI threatens to overwrite all human grief with synthetic perfection, a haunted memory diver races against the clock to stop the digital reset.',
                'category' => null,
                'rating' => null,
                'author' => null,
                'series_label' => null,
                'image_position' => null,
                'position' => 3,
                'cover_path' => 'resources/js/assets/featured/anima.png',
            ],
            [
                'section' => ComingSoonStorySectionEnum::FEATURED_WORLDS->value,
                'title' => "Alice's Adventures in Wonderland",
                'slug' => 'alice-in-wonderland',
                'teaser' => 'Follow Alice into a curious world of talking cats, mad tea parties, and impossible adventures where every path leads somewhere unexpected.',
                'category' => null,
                'rating' => null,
                'author' => null,
                'series_label' => null,
                'image_position' => null,
                'position' => 4,
                'cover_path' => 'resources/js/assets/featured/alice.png',
            ],
            [
                'section' => ComingSoonStorySectionEnum::FEATURED_WORLDS->value,
                'title' => 'Dracula',
                'slug' => 'dracula',
                'teaser' => 'Step into a world where love, faith, and reason are tested by a hunger older than death.',
                'category' => null,
                'rating' => null,
                'author' => null,
                'series_label' => null,
                'image_position' => null,
                'position' => 5,
                'cover_path' => 'resources/js/assets/featured/dracula.png',
            ],
            [
                'section' => ComingSoonStorySectionEnum::FEATURED_WORLDS->value,
                'title' => 'Pride and Prejudice',
                'slug' => 'pride-and-prejudice',
                'teaser' => 'In a world ruled by reputation, romance, and social expectation, Elizabeth Bennet must navigate pride, misunderstanding, and the dangerous possibility of falling in love.',
                'category' => null,
                'rating' => null,
                'author' => null,
                'series_label' => null,
                'image_position' => null,
                'position' => 6,
                'cover_path' => 'resources/js/assets/featured/Pride-prejudice.png',
            ],
            [
                'section' => ComingSoonStorySectionEnum::FEATURED_WORLDS->value,
                'title' => 'Treasure Island',
                'slug' => 'treasure-island',
                'teaser' => 'Every choice at sea carries a price: who to trust, when to run, and what kind of courage survives betrayal. The map is only the beginning.',
                'category' => null,
                'rating' => null,
                'author' => null,
                'series_label' => null,
                'image_position' => null,
                'position' => 7,
                'cover_path' => 'resources/js/assets/featured/treasure.png',
            ],
            ...$this->launchNewStoryPresets(),
            [
                'section' => ComingSoonStorySectionEnum::COMING_SOON->value,
                'title' => 'Jane Eyre',
                'slug' => 'jane-eyre',
                'teaser' => 'A young orphan enters a dark and mysterious estate where buried secrets, dangerous love, and the search for belonging may change the course of her life forever.',
                'category' => null,
                'rating' => null,
                'author' => null,
                'series_label' => null,
                'image_position' => null,
                'position' => 1,
                'cover_path' => 'resources/js/assets/commingSoon/jane-comming.png',
            ],
            [
                'section' => ComingSoonStorySectionEnum::COMING_SOON->value,
                'title' => 'Frankenstein',
                'slug' => 'frankenstein',
                'teaser' => 'Step inside a world where creation, rejection, and consequence follow you like a shadow.',
                'category' => null,
                'rating' => null,
                'author' => null,
                'series_label' => null,
                'image_position' => null,
                'position' => 2,
                'cover_path' => 'resources/js/assets/commingSoon/frankstein-comming.png',
            ],
            [
                'section' => ComingSoonStorySectionEnum::COMING_SOON->value,
                'title' => '20,000 Leagues Under the Sea',
                'slug' => '20000-leagues-under-the-sea',
                'teaser' => 'Step aboard the Nautilus, where each choice pulls you deeper into beauty, danger, and the mystery of Captain Nemo.',
                'category' => null,
                'rating' => null,
                'author' => null,
                'series_label' => null,
                'image_position' => null,
                'position' => 3,
                'cover_path' => 'resources/js/assets/commingSoon/undersea-comming.JPG',
            ],
            [
                'section' => ComingSoonStorySectionEnum::COMING_SOON->value,
                'title' => 'Romeo & Juliet',
                'slug' => 'romeo-and-juliet',
                'teaser' => 'A masked room. A borrowed name. A city holding its breath. Somewhere in the dark of Verona, love discovers it has enemies.',
                'category' => null,
                'rating' => null,
                'author' => null,
                'series_label' => null,
                'image_position' => null,
                'position' => 4,
                'cover_path' => 'resources/js/assets/commingSoon/romeo-comming.png',
            ],
        ];
    }

    /**
     * @return list<array{
     *   section: string,
     *   title: string,
     *   slug: string,
     *   teaser: string,
     *   category: string|null,
     *   rating: string|null,
     *   author: string|null,
     *   series_label: string|null,
     *   image_position: string|null,
     *   position: int,
     *   cover_path: string
     * }>
     */
    private function launchNewStoryPresets(): array
    {
        /** @var array{titles: list<array{title: string, slug: string, teaser: string, category: string, rating: string}>} $manifest */
        $manifest = require database_path('stories/launch/manifest.php');
        $covers = $this->newStoriesCoverPaths();
        $presets = [];

        foreach ($manifest['titles'] as $index => $title) {
            $slug = $title['slug'];
            $coverPath = $covers[$slug] ?? null;

            if ($coverPath === null) {
                $this->warn("Skipping new-stories preset for [{$slug}] — no cover mapped.");

                continue;
            }

            $presets[] = [
                'section' => ComingSoonStorySectionEnum::NEW_STORIES->value,
                'title' => $title['title'],
                'slug' => $slug,
                'teaser' => $title['teaser'],
                'category' => $title['category'],
                'rating' => $this->formatLaunchRatingLabel($title['rating']),
                'author' => null,
                'series_label' => null,
                'image_position' => null,
                'position' => $index + 1,
                'cover_path' => $coverPath,
            ];
        }

        return $presets;
    }

    /**
     * @return array<string, string>
     */
    private function newStoriesCoverPaths(): array
    {
        return [
            'the-adventure-of-the-speckled-band' => 'resources/js/assets/newStories/sherlock-new.png',
            'the-adventure-of-the-blue-carbuncle' => 'resources/js/assets/top-moods/Sherlock in the speckled band 16_9 untitled.png',
            'a-scandal-in-bohemia' => 'resources/js/assets/top-moods/Sherlock in the speckled band 16_9 untitled.png',
            'the-wonderful-wizard-of-oz' => 'resources/js/assets/newStories/Oz landscape titled.png',
            'the-marvelous-land-of-oz' => 'resources/js/assets/top-moods/Oz 16_9 untitled.png',
            'theseus-and-the-minotaur' => 'resources/js/assets/featured/dracula.png',
            'orpheus-and-eurydice' => 'resources/js/assets/featured/dracula.png',
            'havoc-in-heaven' => 'resources/js/assets/featured/dracula.png',
            'the-white-bone-demon' => 'resources/js/assets/featured/dracula.png',
            'pride-and-prejudice' => 'resources/js/assets/newStories/Pride Landscape art only.png',
            'emma' => 'resources/js/assets/top-moods/Pride prejudice 16_9 untitled.png',
            'anima-machina' => 'resources/js/assets/top-moods/Anima machina 16_9 untitled.png',
            'wasteland' => 'resources/js/assets/top-moods/Wasteland wide 16_9 untitled.JPG',
            'driftheart' => 'resources/js/assets/featured/anima.png',
            'dream-police' => 'resources/js/assets/featured/nocturne.png',
            'the-unwritten-ones' => 'resources/js/assets/newStories/New stories 1- 2x.jpg',
            'momotaro-the-peach-boy' => 'resources/js/assets/featured/momotaro.png',
            'the-tell-tale-heart' => 'resources/js/assets/newStories/Tell Tale 5_3 landscape.png',
        ];
    }

    private function formatLaunchRatingLabel(string $rating): string
    {
        return match ($rating) {
            'everyone' => 'Everyone',
            'teen' => 'Teen',
            'young-adult' => 'Young Adult',
            'mature' => 'Mature',
            default => ucfirst(str_replace('-', ' ', $rating)),
        };
    }

    private function buildCoverFileName(ComingSoonStory $record, string $sourcePath): string
    {
        $extension = File::extension($sourcePath) ?: 'png';
        $slug = $record->slug ?: Str::slug($record->title);
        $name = Str::slug($record->section->value.'-'.$slug);

        return "{$name}.{$extension}";
    }
}
