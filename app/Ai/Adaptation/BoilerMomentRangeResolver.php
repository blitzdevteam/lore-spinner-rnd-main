<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * Resolve the moment count range a Boiler Draft can actually satisfy.
 *
 * Every moment needs at least one source event and events are never reused, so
 * the number of playable events is a hard ceiling on the moment count. The Story
 * Session Map sets its recommended range before entry-point diagnosis has run, so
 * it cannot know how much of the session gets cut as pre-entry material. When the
 * cut is deep enough, the recommended minimum becomes unsatisfiable and the Draft
 * agent thrashes between validation errors looking for an escape that does not
 * exist. Clamping to the playable count keeps the prompt and the validator asking
 * for the same reachable number.
 */
final class BoilerMomentRangeResolver
{
    /**
     * @param  array<string, mixed>  $guidance
     * @param  Collection<int, Event>  $events
     * @return array{min: int, max: int, playable_event_count: int, clamped: bool}
     */
    public static function resolve(array $guidance, Collection $events): array
    {
        $recommendedMin = (int) ($guidance['recommended_moment_count_min'] ?? 1);
        $recommendedMax = (int) ($guidance['recommended_moment_count_max'] ?? 0);

        $playableCount = $events
            ->reject(fn (Event $event): bool => (bool) $event->getAttribute('before_entry_point'))
            ->count();

        // A session with no entry-point cut keeps its authored range untouched.
        $ceiling = max(1, $playableCount);

        $max = $recommendedMax > 0 ? min($recommendedMax, $ceiling) : $ceiling;
        $min = min(max(1, $recommendedMin), $max);

        return [
            'min' => $min,
            'max' => $max,
            'playable_event_count' => $playableCount,
            'clamped' => $min !== $recommendedMin || $max !== $recommendedMax,
        ];
    }
}
