<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

/**
 * Deterministic Draft repairs before validation.
 *
 * `boiler_version` and `session_number` are stamped rather than trusted. Both are
 * values the caller already knows, so a model that echoes them loosely must not cost
 * a full regeneration. Drafting the wrong session is still caught: every moment_key
 * must match S{session_number}_M{nn} and every source event must belong to the
 * session's own event set.
 *
 * When a moment is missing source_event_ids but event_role_map rows bind
 * events to that moment_key, fill the IDs from the role map. When moments
 * already list source_event_ids, those bindings are authoritative and the
 * role map's included_moment_key is synced to match. Never invent event IDs
 * that are not already owned by the draft.
 */
final class BoilerDraftCanonicalizer
{
    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function canonicalize(array $draft, int $sessionNumber): array
    {
        $draft['boiler_version'] = '1.0';
        $draft['session_number'] = $sessionNumber;

        $roles = (array) ($draft['event_role_map'] ?? []);
        $moments = (array) ($draft['moment_drafts'] ?? []);

        $idsByMoment = [];
        foreach ($roles as $role) {
            if (! is_array($role) || ! is_int($role['event_id'] ?? null)) {
                continue;
            }

            $momentKey = mb_trim((string) ($role['included_moment_key'] ?? ''));
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

        $eventToMoment = [];
        foreach ($moments as $moment) {
            if (! is_array($moment)) {
                continue;
            }

            $momentKey = (string) ($moment['moment_key'] ?? '');
            if ($momentKey === '') {
                continue;
            }

            foreach ((array) ($moment['source_event_ids'] ?? []) as $sourceId) {
                if (is_int($sourceId)) {
                    $eventToMoment[$sourceId] = $momentKey;
                }
            }
        }

        foreach ($roles as $index => $role) {
            if (! is_array($role) || ! is_int($role['event_id'] ?? null)) {
                continue;
            }

            $boundMoment = $eventToMoment[$role['event_id']] ?? null;
            if (! is_string($boundMoment) || $boundMoment === '') {
                continue;
            }

            if (($role['included_moment_key'] ?? null) !== $boundMoment) {
                $role['included_moment_key'] = $boundMoment;
                $roles[$index] = $role;
            }
        }

        $draft['event_role_map'] = $roles;

        return $this->neutralizeProseFields($draft);
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function neutralizeProseFields(array $draft): array
    {
        if (is_string($draft['playable_promise'] ?? null)) {
            $draft['playable_promise'] = BannedProseValidator::neutralizeBannedPunctuation($draft['playable_promise']);
        }

        foreach ((array) ($draft['moment_drafts'] ?? []) as $index => $moment) {
            if (! is_array($moment)) {
                continue;
            }

            foreach (['runtime_goal', 'live_pressure', 'treatment'] as $field) {
                if (is_string($moment[$field] ?? null)) {
                    $moment[$field] = BannedProseValidator::neutralizeBannedPunctuation($moment[$field]);
                }
            }

            if (isset($moment['source_extracts']) && is_array($moment['source_extracts'])) {
                $moment['source_extracts'] = array_map(
                    fn ($extract): mixed => is_string($extract)
                        ? BannedProseValidator::neutralizeBannedPunctuation($extract)
                        : $extract,
                    $moment['source_extracts'],
                );
            }

            $draft['moment_drafts'][$index] = $moment;
        }

        foreach ((array) ($draft['event_role_map'] ?? []) as $index => $role) {
            if (! is_array($role)) {
                continue;
            }

            foreach (['why', 'reintroduction_note'] as $field) {
                if (is_string($role[$field] ?? null)) {
                    $role[$field] = BannedProseValidator::neutralizeBannedPunctuation($role[$field]);
                }
            }

            $draft['event_role_map'][$index] = $role;
        }

        foreach ((array) ($draft['cut_material'] ?? []) as $index => $cut) {
            if (! is_array($cut)) {
                continue;
            }

            foreach (['reason', 'reintroduction_route'] as $field) {
                if (is_string($cut[$field] ?? null)) {
                    $cut[$field] = BannedProseValidator::neutralizeBannedPunctuation($cut[$field]);
                }
            }

            $draft['cut_material'][$index] = $cut;
        }

        return $draft;
    }
}
