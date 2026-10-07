<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use App\Models\SessionAdaptation;

/**
 * What the next session opens with, so a session's close and its next-session
 * fields hand off to the opening the player will actually read.
 *
 * Every session's Entry Point Diagnosis runs before any session's later phases,
 * so the next opening exists whenever a close is written.
 */
final class SessionHandoff
{
    public static function isFinalSession(SessionAdaptation $session): bool
    {
        return ! SessionAdaptation::query()
            ->where('story_adaptation_id', $session->story_adaptation_id)
            ->where('session_number', '>', $session->session_number)
            ->exists();
    }

    /**
     * @return array{session_number: int, cold_open: string, opening_situation: string, start_event_position: int|null}|null
     */
    public static function nextSessionOpening(SessionAdaptation $session): ?array
    {
        $next = SessionAdaptation::query()
            ->where('story_adaptation_id', $session->story_adaptation_id)
            ->where('session_number', $session->session_number + 1)
            ->first();

        $diagnosis = (array) ($next?->entry_point_diagnosis ?? []);
        $coldOpen = mb_trim((string) ($diagnosis['cold_open'] ?? ''));

        if ($next === null || $coldOpen === '') {
            return null;
        }

        return [
            'session_number' => (int) $next->session_number,
            'cold_open' => $coldOpen,
            'opening_situation' => (string) data_get($diagnosis, 'situation_stakes_world.established', ''),
            'start_event_position' => isset($diagnosis['start_event_position']) ? (int) $diagnosis['start_event_position'] : null,
        ];
    }
}
