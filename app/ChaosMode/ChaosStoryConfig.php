<?php

declare(strict_types=1);

namespace App\ChaosMode;

use App\Support\LaunchCatalog;
use App\Support\StoryPlayability;
use App\Tts\TtsVoiceCatalog;

/**
 * Whitelist + creative metadata for chaos-mode-enabled stories.
 *
 * Pipeline Upgrade V2 (Daniel's correction, 2026-05-24): the per-story
 * `voice_partial` is GONE. Chaos Mode now always reads the cached
 * `session_adaptations.runtime_narrator_prompt` produced by Deliverable 8's
 * RuntimeNarratorTemplateBuilder. There is no fallback to the legacy
 * `resources/views/ai/agents/chaos/partials/*.blade.php` blocks. Old chaos
 * sessions belonging to stories that have not yet been re-adapted under V2
 * will be unplayable until the pipeline is re-run for that story.
 *
 * Each entry binds:
 *   - the story `slug` (matches `stories.slug` in the DB)
 *   - the human-readable `title` for the start screen
 *   - the `protagonist` first name (used by the AGENCY HANDOFF prompt so the
 *     narrator says "What does Watson do?" not "What does Alice do?")
 *   - a short `tagline` for the story selector UI
 *
 * Narration voice defaults live in config/tts-voices.php and are resolved
 * through TtsVoiceCatalog (used by both Chaos Mode and Story Guard).
 */
final class ChaosStoryConfig
{
    /**
     * @return array<int, array{slug:string, title:string, protagonist:string, tagline:string, prefer_literal_opening?: bool}>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => 'alices-adventures-in-wonderland',
                'title' => "Alice's Adventures in Wonderland",
                'protagonist' => 'Alice',
                'tagline' => 'Carroll — full agency through Wonderland.',
            ],
            [
                'slug' => 'the-adventure-of-the-speckled-band',
                'title' => 'Sherlock Holmes: The Speckled Band',
                'protagonist' => 'Dr. John Watson',
                'tagline' => 'Doyle — investigate beside Holmes.',
            ],
            [
                'slug' => 'the-adventure-of-the-blue-carbuncle',
                'title' => 'Sherlock Holmes: The Blue Carbuncle',
                'protagonist' => 'Dr. John Watson',
                'tagline' => 'Doyle — investigate beside Holmes.',
            ],
            [
                'slug' => 'a-scandal-in-bohemia',
                'title' => 'Sherlock Holmes: A Scandal in Bohemia',
                'protagonist' => 'Dr. John Watson',
                'tagline' => 'Doyle — investigate beside Holmes.',
            ],
            [
                'slug' => 'the-tell-tale-heart',
                'title' => 'The Tell-Tale Heart: Beneath the Floor',
                'protagonist' => 'The Narrator',
                'tagline' => 'Poe — descend into the cracked mind.',
            ],
            [
                'slug' => 'dr-jekyll-and-mr-hyde',
                'title' => 'Dr. Jekyll and Mr. Hyde: The Other Self',
                'protagonist' => 'Dr. Henry Jekyll',
                'tagline' => 'Stevenson — hold the line between self and shadow.',
            ],
            [
                'slug' => 'nocturne',
                'title' => 'Nocturne',
                'protagonist' => 'Akira',
                'tagline' => 'Wittmer — vanish into Tokyo\'s shadow-house.',
                'prefer_literal_opening' => true,
            ],
            [
                'slug' => 'anima-machina',
                'title' => 'Anima Machina',
                'protagonist' => 'Nora',
                'tagline' => 'Wittmer — dive grief in the neon archive.',
                'prefer_literal_opening' => true,
            ],
            [
                'slug' => 'driftheart',
                'title' => 'Driftheart',
                'protagonist' => 'Kataria',
                'tagline' => 'Wittmer — fall from the sky-villa into the Drift.',
            ],
            [
                'slug' => 'the-matrix',
                'title' => 'The Matrix',
                'protagonist' => 'Neo',
                'tagline' => 'Wachowski — wake up and choose the real world.',
            ],
            [
                'slug' => 'the-snow-queen',
                'title' => 'The Snow Queen',
                'protagonist' => 'Gerda',
                'tagline' => 'Andersen — walk north through winter to find Kay.',
            ],
            [
                'slug' => 'the-masque-of-the-red-death',
                'title' => 'The Masque of the Red Death',
                'protagonist' => 'Prospero',
                'tagline' => 'Poe — hide from the plague behind abbey walls.',
            ],
            [
                'slug' => 'the-wonderful-wizard-of-oz',
                'title' => 'The Wonderful Wizard of Oz: The Road Home',
                'protagonist' => 'Dorothy Gale',
                'tagline' => 'Baum — follow the yellow brick road to the Emerald City.',
            ],
            [
                'slug' => 'wasteland',
                'title' => 'Wasteland',
                'protagonist' => 'Emmit',
                'tagline' => 'Soares — survive the desert built from the world\'s castoffs.',
            ],
            [
                'slug' => 'pjs',
                'title' => "PJ's",
                'protagonist' => 'Stills',
                'tagline' => 'FREEP1 — jump into the hardest battlefield there is.',
            ],
            [
                'slug' => 'momotaro-the-peach-boy',
                'title' => 'Momotaro: The Peach Boy',
                'protagonist' => 'Momotaro',
                'tagline' => 'Japanese folktale — sail to Oni Island and face the demons.',
                'prefer_literal_opening' => true,
            ],
            [
                'slug' => 'the-unwritten-ones',
                'title' => 'The Unwritten Ones',
                'protagonist' => 'Leo',
                'tagline' => 'Wittmer — a carnival of endings, and names that can vanish.',
                'prefer_literal_opening' => true,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return array_map(fn (array $row) => $row['slug'], self::all());
    }

    /**
     * @return array{slug:string, title:string, protagonist:string, tagline:string, prefer_literal_opening?: bool}|null
     */
    public static function find(string $slug): ?array
    {
        $canonical = StoryPlayability::canonicalSlug($slug);

        foreach (self::all() as $row) {
            if ($row['slug'] === $slug || $row['slug'] === $canonical) {
                return self::applyLaunchProtagonist($row, $canonical);
            }
        }

        $launchProtagonist = LaunchCatalog::protagonist($slug);

        if ($launchProtagonist === null) {
            return null;
        }

        $launchTitle = LaunchCatalog::displayTitle($canonical, $canonical);

        return [
            'slug' => $canonical,
            'title' => $launchTitle,
            'protagonist' => $launchProtagonist,
            'tagline' => '',
        ];
    }

    /**
     * Resolve the ElevenLabs voice ID for a story slug.
     */
    public static function ttsVoiceId(string $slug): string
    {
        return TtsVoiceCatalog::recommendedForSlug($slug);
    }

    /**
     * Canonical playable protagonist. ChaosStoryConfig wins over extracted names
     * so Baker Street titles stay Watson even when the source centers Holmes.
     */
    public static function protagonistFor(string $slug, ?string $fallback = null): string
    {
        return LaunchCatalog::protagonist($slug)
            ?? self::find($slug)['protagonist']
            ?? $fallback
            ?? 'the protagonist';
    }

    /**
     * @param  array{slug:string, title:string, protagonist:string, tagline:string, prefer_literal_opening?: bool}  $row
     * @return array{slug:string, title:string, protagonist:string, tagline:string, prefer_literal_opening?: bool}
     */
    private static function applyLaunchProtagonist(array $row, string $canonicalSlug): array
    {
        $launchProtagonist = LaunchCatalog::protagonist($canonicalSlug);

        if ($launchProtagonist !== null) {
            $row['protagonist'] = $launchProtagonist;
        }

        return $row;
    }
}
