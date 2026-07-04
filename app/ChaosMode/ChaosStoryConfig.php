<?php

declare(strict_types=1);

namespace App\ChaosMode;

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
 *   - `tts_voice_id` — the ElevenLabs voice ID used for TTS in BOTH Chaos Mode
 *     and the main Story Guard mode. null = fall back to
 *     config('services.elevenlabs.voice_id').
 */
final class ChaosStoryConfig
{
    /**
     * Declan Sage voice (kqVT88a5QfII1HNAEPTJ) — used for Anima Machina, Driftheart, and The Matrix.
     */
    private const VOICE_DECLAN_SAGE = 'kqVT88a5QfII1HNAEPTJ';

    /**
     * Davidof voice (TWutjvRaJqAX89preB4e) — used for Nocturne.
     */
    private const VOICE_DAVIDOF = 'TWutjvRaJqAX89preB4e';

    /**
     * @return array<int, array{slug:string, title:string, protagonist:string, tagline:string, tts_voice_id:string|null, prefer_literal_opening?: bool}>
     */
    public static function all(): array
    {
        return [
            [
                'slug'          => 'alices-adventures-in-wonderland',
                'title'         => "Alice's Adventures in Wonderland",
                'protagonist'   => 'Alice',
                'tagline'       => 'Carroll — full agency through Wonderland.',
                'tts_voice_id'  => null,
            ],
            [
                'slug'          => 'the-adventure-of-the-speckled-band',
                'title'         => 'Sherlock Holmes in The Speckled Band',
                'protagonist'   => 'Watson',
                'tagline'       => 'Doyle — investigate beside Holmes.',
                'tts_voice_id'  => null,
            ],
            [
                'slug'          => 'the-tell-tale-heart',
                'title'         => 'The Tell-Tale Heart',
                'protagonist'   => 'the Narrator',
                'tagline'       => 'Poe — descend into the cracked mind.',
                'tts_voice_id'  => null,
            ],
            [
                'slug'                   => 'nocturne',
                'title'                  => 'Nocturne',
                'protagonist'            => 'Akira',
                'tagline'                => 'Wittmer — vanish into Tokyo\'s shadow-house.',
                'tts_voice_id'           => self::VOICE_DAVIDOF,
                'prefer_literal_opening' => true,
            ],
            [
                'slug'                  => 'anima-machina',
                'title'                 => 'Anima Machina',
                'protagonist'           => 'Nora',
                'tagline'               => 'Wittmer — dive grief in the neon archive.',
                'tts_voice_id'          => self::VOICE_DECLAN_SAGE,
                'prefer_literal_opening' => true,
            ],
            [
                'slug'          => 'driftheart',
                'title'         => 'Driftheart',
                'protagonist'   => 'Kataria',
                'tagline'       => 'Wittmer — fall from the sky-villa into the Drift.',
                'tts_voice_id'  => self::VOICE_DECLAN_SAGE,
            ],
            [
                'slug'          => 'the-matrix',
                'title'         => 'The Matrix',
                'protagonist'   => 'Neo',
                'tagline'       => 'Wachowski — wake up and choose the real world.',
                'tts_voice_id'  => self::VOICE_DECLAN_SAGE,
            ],
            [
                'slug'          => 'the-snow-queen',
                'title'         => 'The Snow Queen',
                'protagonist'   => 'Gerda',
                'tagline'       => 'Andersen — walk north through winter to find Kay.',
                'tts_voice_id'  => null,
            ],
            [
                'slug'          => 'the-masque-of-the-red-death',
                'title'         => 'The Masque of the Red Death',
                'protagonist'   => 'Prospero',
                'tagline'       => 'Poe — hide from the plague behind abbey walls.',
                'tts_voice_id'  => null,
            ],
            [
                'slug'          => 'the-wonderful-wizard-of-oz',
                'title'         => 'The Wonderful Wizard of Oz',
                'protagonist'   => 'Dorothy',
                'tagline'       => 'Baum — follow the yellow brick road to the Emerald City.',
                'tts_voice_id'  => null,
            ],
            [
                'slug'          => 'wasteland',
                'title'         => 'Wasteland',
                'protagonist'   => 'Emmit',
                'tagline'       => 'Soares — survive the desert built from the world\'s castoffs.',
                'tts_voice_id'  => null,
            ],
            [
                'slug'          => 'pjs',
                'title'         => "PJ's",
                'protagonist'   => 'Stills',
                'tagline'       => 'FREEP1 — jump into the hardest battlefield there is.',
                'tts_voice_id'  => null,
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
     * @return array{slug:string, title:string, protagonist:string, tagline:string, tts_voice_id:string|null, prefer_literal_opening?: bool}|null
     */
    public static function find(string $slug): ?array
    {
        foreach (self::all() as $row) {
            if ($row['slug'] === $slug) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Resolve the ElevenLabs voice ID for a story slug.
     * Returns the story-specific ID when one is configured, otherwise the
     * application-wide default from config('services.elevenlabs.voice_id').
     */
    public static function ttsVoiceId(string $slug): string
    {
        $row = self::find($slug);

        return $row['tts_voice_id'] ?? (string) config('services.elevenlabs.voice_id');
    }
}
