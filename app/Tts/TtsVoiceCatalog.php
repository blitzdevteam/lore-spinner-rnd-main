<?php

declare(strict_types=1);

namespace App\Tts;

use App\Models\Game;
use App\Support\LaunchCatalog;

final class TtsVoiceCatalog
{
    /**
     * @return array<int, array{id: string, label: string, description: string, gender: string}>
     */
    public static function options(): array
    {
        $voices = config('tts-voices.voices', []);

        if (! is_array($voices)) {
            return [];
        }

        $options = [];

        foreach ($voices as $voice) {
            if (! is_array($voice)) {
                continue;
            }

            $id = $voice['id'] ?? null;

            if (! is_string($id) || $id === '') {
                continue;
            }

            $gender = $voice['gender'] ?? 'male';

            $options[] = [
                'id' => $id,
                'label' => is_string($voice['label'] ?? null) && $voice['label'] !== ''
                    ? $voice['label']
                    : $id,
                'description' => is_string($voice['description'] ?? null) ? $voice['description'] : '',
                'gender' => $gender === 'female' ? 'female' : 'male',
            ];
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public static function allowedIds(): array
    {
        return array_map(
            fn (array $voice): string => $voice['id'],
            self::options(),
        );
    }

    public static function isAllowed(string $voiceId): bool
    {
        return in_array($voiceId, self::allowedIds(), true);
    }

    /**
     * Resolve the voice ID for a TTS request.
     *
     * An empty/missing client value means the story's recommended voice.
     * A non-empty value must be on the allow-list or the recommended voice
     * is used instead.
     */
    public static function resolveForGame(mixed $requestedVoiceId, Game $game): string
    {
        $recommended = self::recommendedForGame($game);

        if (! is_string($requestedVoiceId) || $requestedVoiceId === '') {
            return $recommended;
        }

        return self::isAllowed($requestedVoiceId) ? $requestedVoiceId : $recommended;
    }

    public static function recommendedForGame(Game $game): string
    {
        $slug = $game->story?->slug;
        $worldSlugs = [];

        if ($game->relationLoaded('story') && $game->story?->relationLoaded('worlds')) {
            foreach ($game->story->worlds as $world) {
                if (is_string($world->slug) && $world->slug !== '') {
                    $worldSlugs[] = $world->slug;
                }
            }
        }

        return self::recommendedForSlug(is_string($slug) ? $slug : '', $worldSlugs);
    }

    /**
     * @param  array<int, string>  $worldSlugs
     */
    public static function recommendedForSlug(string $slug, array $worldSlugs = []): string
    {
        $storyDefault = self::mapValue('tts-voices.story_defaults', $slug);

        if ($storyDefault !== null) {
            return $storyDefault;
        }

        $catalogWorld = LaunchCatalog::worldSlugForStory($slug);

        if (is_string($catalogWorld)) {
            $worldDefault = self::mapValue('tts-voices.world_defaults', $catalogWorld);

            if ($worldDefault !== null) {
                return $worldDefault;
            }
        }

        foreach ($worldSlugs as $worldSlug) {
            $worldDefault = self::mapValue('tts-voices.world_defaults', $worldSlug);

            if ($worldDefault !== null) {
                return $worldDefault;
            }
        }

        return self::fallbackId();
    }

    public static function fallbackId(): string
    {
        $fallback = config('tts-voices.fallback');

        if (is_string($fallback) && $fallback !== '' && self::isAllowed($fallback)) {
            return $fallback;
        }

        $ids = self::allowedIds();

        if ($ids !== []) {
            return $ids[0];
        }

        return (string) config('services.elevenlabs.voice_id');
    }

    private static function mapValue(string $configKey, string $mapKey): ?string
    {
        $map = config($configKey, []);

        if (! is_array($map)) {
            return null;
        }

        $value = $map[$mapKey] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
