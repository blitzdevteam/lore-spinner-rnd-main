<?php

declare(strict_types=1);

namespace App\Tts;

use App\ChaosMode\ChaosStoryConfig;
use App\Models\Game;

final class TtsVoiceCatalog
{
    /**
     * @return array<int, array{id: string, label: string, description: string}>
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

            $options[] = [
                'id' => $id,
                'label' => is_string($voice['label'] ?? null) && $voice['label'] !== ''
                    ? $voice['label']
                    : $id,
                'description' => is_string($voice['description'] ?? null) ? $voice['description'] : '',
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

        return ChaosStoryConfig::ttsVoiceId(is_string($slug) ? $slug : '');
    }
}
