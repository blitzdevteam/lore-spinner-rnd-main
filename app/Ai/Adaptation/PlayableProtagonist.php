<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use App\ChaosMode\ChaosStoryConfig;
use App\Models\Story;

/**
 * The one character the player plays, as every adaptation phase must name it.
 */
final class PlayableProtagonist
{
    public static function for(Story $story): string
    {
        $adaptation = $story->adaptation;
        $map = (array) ($adaptation?->story_session_map ?? []);
        $audit = (array) ($adaptation?->ip_audit ?? []);
        $fallback = mb_trim((string) ($map['protagonist'] ?? $audit['protagonist'] ?? ''));

        return ChaosStoryConfig::protagonistFor($story->slug, $fallback !== '' ? $fallback : null);
    }
}
