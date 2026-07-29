<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

/**
 * Deterministic Draft repairs before validation.
 *
 * When a moment is missing source_event_ids but event_role_map rows bind
 * events to that moment_key, fill the IDs from the role map. Never invent
 * event IDs that are not already owned by the draft.
 */
final class BoilerDraftCanonicalizer
{
    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function canonicalize(array $draft): array
    {
        $roles = (array) ($draft['event_role_map'] ?? []);
        $moments = (array) ($draft['moment_drafts'] ?? []);

        $idsByMoment = [];
        foreach ($roles as $role) {
            if (! is_array($role) || ! is_int($role['event_id'] ?? null)) {
                continue;
            }

            $momentKey = trim((string) ($role['included_moment_key'] ?? ''));
            if ($momentKey === '') {
                continue;
            }

            $idsByMoment[$momentKey][] = $role['event_id'];
        }

        foreach ($moments as $index => $moment) {
            if (! is_array($moment)) {
                continue;
            }

            $momentKey = (string) ($moment['moment_key'] ?? '');
            $sourceIds = $moment['source_event_ids'] ?? null;
            $needsFill = ! is_array($sourceIds) || $sourceIds === [];

            if ($needsFill && $momentKey !== '' && ! empty($idsByMoment[$momentKey])) {
                $moment['source_event_ids'] = array_values(array_unique($idsByMoment[$momentKey]));
            }

            $anchor = $moment['anchor_event_id'] ?? null;
            $filled = (array) ($moment['source_event_ids'] ?? []);

            if ($filled === [] && is_int($anchor)) {
                $filled = [$anchor];
            }

            if (
                is_int($anchor)
                && $filled !== []
                && ! in_array($anchor, $filled, true)
            ) {
                array_unshift($filled, $anchor);
            }

            if ($filled !== []) {
                $moment['source_event_ids'] = array_values(array_unique($filled));
            }

            $moments[$index] = $moment;
        }

        $draft['moment_drafts'] = $moments;

        return $draft;
    }
}
