<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Story;
use Illuminate\Database\Seeder;

/**
 * Update story teasers without re-running extraction pipelines.
 *
 * Usage: php artisan db:seed --class=UpdateStoryTeasersSeeder --force
 */
final class UpdateStoryTeasersSeeder extends Seeder
{
    /**
     * @return array<string, string>
     */
    private function teasersBySlug(): array
    {
        return [
            'the-tell-tale-heart' => "As guilt begins to twist reality around him, a man struggles to silence the terrifying sound he cannot escape: the beating of a dead man's heart.",
            'the-masque-of-the-red-death' => 'Behind locked gates and glittering masks, a night of celebration slowly transforms into a nightmare no one can escape.',
            'the-wonderful-wizard-of-oz' => 'A storm carries you into the magical land of Oz, where witches whisper, lions tremble, and every step down the Yellow Brick Road changes who you are becoming.',
            'the-adventure-of-the-speckled-band' => 'A young woman fears she will suffer the same fate as her sister, forcing Sherlock Holmes to confront a mystery hidden behind locked doors and deadly secrets.',
            'nocturne' => 'Beyond the rain-soaked glass walls of Nocturne, Akira finds herself trapped inside a system where identities are rewritten and nothing is quite as voluntary as it seems.',
            'wasteland' => "Abandoned in a desert built from humanity's castoffs, an engineer must decide whether to escape or help the people that the world chose to forget.",
            'pride-and-prejudice' => 'In a world ruled by reputation, romance, and social expectation, Elizabeth Bennet must navigate pride, misunderstanding, and the dangerous possibility of falling in love.',
            'alice-in-wonderland' => 'Follow Alice into a curious world of talking cats, mad tea parties, and impossible adventures where every path leads somewhere unexpected.',
            'alices-adventures-in-wonderland' => 'Follow Alice into a curious world of talking cats, mad tea parties, and impossible adventures where every path leads somewhere unexpected.',
            'dr-jekyll-and-mr-hyde' => "Beneath the fog-covered streets of Victorian London, a terrifying secret grows inside Dr. Jekyll's laboratory, threatening to consume everyone around him.",
            'the-strange-case-of-dr-jekyll-and-mr-hyde' => "Beneath the fog-covered streets of Victorian London, a terrifying secret grows inside Dr. Jekyll's laboratory, threatening to consume everyone around him.",
            'jane-eyre' => 'A young orphan enters a dark and mysterious estate where buried secrets, dangerous love, and the search for belonging may change the course of her life forever.',
            'anima-machina' => 'When a sentient AI threatens to overwrite all human grief with synthetic perfection, a haunted memory diver races against the clock to stop the digital reset.',
            'the-unwritten-ones' => "A carnival that opens one night only promises any story you want. Three siblings follow a vanishing trail of names — and learn that names are knots, and pulling too hard can make you disappear.",
            'pjs' => "A team of elite Air Force PJs discover that the hardest battlefield may be the one where there's no enemy to shoot, only lives to save and ghosts to outrun.",
            'dracula' => 'Step into a world where love, faith, and reason are tested by a hunger older than death.',
            'frankenstein' => 'Step inside a world where creation, rejection, and consequence follow you like a shadow.',
            'romeo-and-juliet' => 'A masked room. A borrowed name. A city holding its breath. Somewhere in the dark of Verona, love discovers it has enemies.',
            'treasure-island' => 'Every choice at sea carries a price: who to trust, when to run, and what kind of courage survives betrayal. The map is only the beginning.',
            '20000-leagues-under-the-sea' => 'Step aboard the Nautilus, where each choice pulls you deeper into beauty, danger, and the mystery of Captain Nemo.',
        ];
    }

    public function run(): void
    {
        foreach ($this->teasersBySlug() as $slug => $teaser) {
            $updated = Story::query()->where('slug', $slug)->update(['teaser' => $teaser]);

            if ($updated > 0) {
                $this->command->info("Updated teaser: {$slug}");
            }
        }

        $this->command->info('Story teaser update complete.');
    }
}
