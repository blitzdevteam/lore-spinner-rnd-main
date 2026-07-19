<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use App\Models\Event;
use App\Models\SessionAdaptation;
use Illuminate\Support\Collection;
use RuntimeException;

final class BoilerDraftValidator
{
    private const array ROLES = [
        'PLAY',
        'PULSE',
        'CHOICE',
        'REACTION',
        'MONTAGE',
        'ECHO',
        'CUT',
    ];

    /**
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $storyMap
     * @param  Collection<int, Event>  $events
     */
    public function validate(
        array $draft,
        SessionAdaptation $session,
        array $storyMap,
        Collection $events,
    ): void {
        if (($draft['boiler_version'] ?? null) !== '1.0') {
            throw new RuntimeException('Boiler Draft requires boiler_version 1.0.');
        }

        if (($draft['session_number'] ?? null) !== $session->session_number) {
            throw new RuntimeException('Boiler Draft session_number does not match its session adaptation.');
        }

        $guidance = collect((array) data_get($storyMap, 'boiling_profile.per_session_guidance', []))
            ->firstWhere('session_number', $session->session_number);
        if (! is_array($guidance)) {
            throw new RuntimeException('Boiler Draft cannot validate without matching Story Session Map guidance.');
        }

        $budget = $draft['session_budget_decision'] ?? null;
        if (! is_array($budget)) {
            throw new RuntimeException('Boiler Draft requires session_budget_decision.');
        }

        $targetCount = $budget['target_moment_count'] ?? null;
        $targetMinutes = $budget['target_playtime_minutes'] ?? null;
        if (! is_int($targetCount) || $targetCount < 1) {
            throw new RuntimeException('Boiler Draft target_moment_count must be a positive integer.');
        }
        if ($targetMinutes !== ($guidance['target_playtime_minutes'] ?? null)) {
            throw new RuntimeException('Boiler Draft target_playtime_minutes must equal Story Session Map guidance.');
        }
        if (
            $targetCount < ($guidance['recommended_moment_count_min'] ?? PHP_INT_MAX)
            || $targetCount > ($guidance['recommended_moment_count_max'] ?? 0)
        ) {
            throw new RuntimeException('Boiler Draft target_moment_count is outside the approved Story Session Map range.');
        }

        foreach (['must_compress_event_ids', 'must_breathe_event_ids'] as $field) {
            $draftIds = (array) ($budget[$field] ?? []);
            $guidanceIds = (array) ($guidance[$field] ?? []);
            sort($draftIds);
            sort($guidanceIds);
            if ($draftIds !== $guidanceIds) {
                throw new RuntimeException("Boiler Draft {$field} must match Story Session Map guidance.");
            }
        }

        $moments = $draft['moment_drafts'] ?? null;
        if (! is_array($moments) || count($moments) !== $targetCount) {
            throw new RuntimeException('Boiler Draft moment count must exactly match target_moment_count.');
        }

        $eventIds = $events->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $roles = $draft['event_role_map'] ?? null;
        if (! is_array($roles) || count($roles) !== count($eventIds)) {
            throw new RuntimeException('Boiler Draft must contain exactly one event role per assigned event.');
        }

        $roleEventIds = [];
        foreach ($roles as $role) {
            if (! is_array($role) || ! is_int($role['event_id'] ?? null)) {
                throw new RuntimeException('Every Boiler event role requires an integer event_id.');
            }

            $eventId = $role['event_id'];
            if (! in_array($eventId, $eventIds, true) || in_array($eventId, $roleEventIds, true)) {
                throw new RuntimeException('Boiler event roles contain an out-of-session or duplicate event.');
            }
            if (! in_array($role['role'] ?? null, self::ROLES, true)) {
                throw new RuntimeException("Boiler event {$eventId} has an invalid role.");
            }
            if (! is_string($role['why'] ?? null) || trim($role['why']) === '') {
                throw new RuntimeException("Boiler event {$eventId} requires a role reason.");
            }
            if (
                in_array($role['role'], ['PLAY', 'PULSE', 'CHOICE'], true)
                && trim((string) ($role['included_moment_key'] ?? '')) === ''
            ) {
                throw new RuntimeException("Playable Boiler event {$eventId} must belong to a moment.");
            }

            $roleEventIds[] = $eventId;
        }

        sort($eventIds);
        sort($roleEventIds);
        if ($eventIds !== $roleEventIds) {
            throw new RuntimeException('Boiler Draft event-role coverage is incomplete.');
        }

        $momentKeys = [];
        $momentSourceEventIds = [];
        foreach ($moments as $index => $moment) {
            if (! is_array($moment)) {
                throw new RuntimeException('Every Boiler moment draft must be an object.');
            }

            $expectedOrder = $index + 1;
            $momentKey = $moment['moment_key'] ?? null;
            if (
                ! is_string($momentKey)
                || ! preg_match('/^S' . $session->session_number . '_M\d{2}$/', $momentKey)
                || in_array($momentKey, $momentKeys, true)
            ) {
                throw new RuntimeException('Boiler moment keys must be unique, session-local, and use S{session}_M{nn}.');
            }
            if (($moment['moment_order'] ?? null) !== $expectedOrder) {
                throw new RuntimeException('Boiler moment_order must be contiguous and one-based.');
            }

            $sourceIds = $moment['source_event_ids'] ?? null;
            if (! is_array($sourceIds) || $sourceIds === []) {
                throw new RuntimeException("Boiler moment {$momentKey} requires source_event_ids.");
            }
            foreach ($sourceIds as $sourceId) {
                if (! is_int($sourceId) || ! in_array($sourceId, $eventIds, true)) {
                    throw new RuntimeException("Boiler moment {$momentKey} references an out-of-session event.");
                }
                if (in_array($sourceId, $momentSourceEventIds, true)) {
                    throw new RuntimeException("Boiler event {$sourceId} is assigned to more than one moment.");
                }

                $role = collect($roles)->firstWhere('event_id', $sourceId);
                if (! is_array($role) || ($role['included_moment_key'] ?? null) !== $momentKey) {
                    throw new RuntimeException("Boiler event {$sourceId} does not bind back to moment {$momentKey}.");
                }
                $momentSourceEventIds[] = $sourceId;
            }

            if (! is_int($moment['anchor_event_id'] ?? null) || ! in_array($moment['anchor_event_id'], $sourceIds, true)) {
                throw new RuntimeException("Boiler moment {$momentKey} requires an in-moment anchor_event_id.");
            }

            foreach (['runtime_goal', 'live_pressure'] as $field) {
                if (! is_string($moment[$field] ?? null) || trim($moment[$field]) === '') {
                    throw new RuntimeException("Boiler moment {$momentKey} requires {$field}.");
                }
            }

            $exitCondition = $moment['exit_condition_draft'] ?? null;
            if (! is_string($exitCondition) || mb_strlen(trim($exitCondition)) < 30 || mb_strlen($exitCondition) > 1_000) {
                throw new RuntimeException("Boiler moment {$momentKey} requires a bounded semantic exit condition.");
            }

            if (! is_array($moment['player_affordances'] ?? null) || $moment['player_affordances'] === []) {
                throw new RuntimeException("Boiler moment {$momentKey} requires player affordances.");
            }
            if (! is_array($moment['source_extracts'] ?? null) || $moment['source_extracts'] === []) {
                throw new RuntimeException("Boiler moment {$momentKey} requires local source extracts.");
            }

            $momentKeys[] = $momentKey;
        }

        foreach ($roles as $role) {
            $includedMomentKey = $role['included_moment_key'] ?? null;
            if ($includedMomentKey !== null && $includedMomentKey !== '' && ! in_array($includedMomentKey, $momentKeys, true)) {
                throw new RuntimeException('Boiler event role references a missing moment.');
            }
        }

        foreach ((array) ($guidance['must_breathe_event_ids'] ?? []) as $eventId) {
            $role = collect($roles)->firstWhere('event_id', $eventId);
            if (! is_array($role)) {
                throw new RuntimeException('A must-breathe event is missing from Boiler role coverage.');
            }
            if (
                in_array($role['role'] ?? null, ['MONTAGE', 'CUT'], true)
                && trim((string) ($role['reintroduction_note'] ?? '')) === ''
            ) {
                throw new RuntimeException('A must-breathe event assigned MONTAGE or CUT requires a reintroduction_note.');
            }
        }

        $arc = $draft['playable_promise_arc'] ?? null;
        if (! is_array($arc)) {
            throw new RuntimeException('Boiler Draft requires playable_promise_arc.');
        }
        foreach (['first_proof_moment', 'midpoint_intensification', 'final_cash_out'] as $field) {
            if (! in_array($arc[$field] ?? null, $momentKeys, true)) {
                throw new RuntimeException("Boiler Draft playable_promise_arc.{$field} must resolve to a drafted moment.");
            }
        }
        if ($arc['final_cash_out'] !== end($momentKeys)) {
            throw new RuntimeException('Boiler Draft final_cash_out must be the final ordered moment.');
        }

        $startEventId = (int) data_get($session->entry_point_diagnosis, 'start_event_id', 0);
        $firstMoment = $moments[0];
        if ($startEventId > 0 && ! in_array($startEventId, $firstMoment['source_event_ids'], true)) {
            throw new RuntimeException('Boiler Draft first moment must include the Entry Point start event.');
        }
    }
}
