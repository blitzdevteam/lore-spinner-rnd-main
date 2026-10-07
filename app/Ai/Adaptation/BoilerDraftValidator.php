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
     * Prose fields in a Boiler Draft to scan for voice bans.
     * Each entry is a dot-notation path into the draft artifact.
     */
    private const array PROSE_PATHS = [
        'playable_promise',
        'moment_drafts.*.runtime_goal',
        'moment_drafts.*.live_pressure',
        'moment_drafts.*.treatment',
        'moment_drafts.*.source_extracts.*',
        'event_role_map.*.why',
        'event_role_map.*.reintroduction_note',
        'cut_material.*.reason',
        'cut_material.*.reintroduction_route',
    ];

    public function __construct(private readonly ?BannedProseValidator $bannedProseValidator = null) {}

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
        $range = BoilerMomentRangeResolver::resolve($guidance, $events);
        if ($targetCount < $range['min'] || $targetCount > $range['max']) {
            throw new RuntimeException(sprintf(
                'Boiler Draft target_moment_count %d is outside the satisfiable range %d-%d '
                .'(%d playable events after the entry point).',
                $targetCount,
                $range['min'],
                $range['max'],
                $range['playable_event_count'],
            ));
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
            if (! is_string($role['why'] ?? null) || mb_trim($role['why']) === '') {
                throw new RuntimeException("Boiler event {$eventId} requires a role reason.");
            }
            if (
                in_array($role['role'], ['PLAY', 'PULSE', 'CHOICE'], true)
                && mb_trim((string) ($role['included_moment_key'] ?? '')) === ''
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
                || ! preg_match('/^S'.$session->session_number.'_M\d{2}$/', $momentKey)
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
                if (! is_string($moment[$field] ?? null) || mb_trim($moment[$field]) === '') {
                    throw new RuntimeException("Boiler moment {$momentKey} requires {$field}.");
                }
            }

            $exitCondition = $moment['exit_condition_draft'] ?? null;
            if (! is_string($exitCondition) || mb_strlen(mb_trim($exitCondition)) < 30 || mb_strlen($exitCondition) > 1_000) {
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
                && mb_trim((string) ($role['reintroduction_note'] ?? '')) === ''
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

        $this->validateStoryOrder($moments, $events);

        $this->validateVoiceBans($draft, $session);
    }

    /**
     * The runtime plays cards in order and cannot go back, and the cold open
     * already covers everything before the entry point. A card that stages a
     * pre-entry event, or starts earlier in the story than the card before it,
     * makes the player live an event twice or out of sequence.
     *
     * @param  list<array<string, mixed>>  $moments
     * @param  Collection<int, Event>  $events
     */
    private function validateStoryOrder(array $moments, Collection $events): void
    {
        $positions = $events->mapWithKeys(fn (Event $event): array => [(int) $event->id => (int) $event->getAttribute('story_position')]);
        $preEntry = $events->filter(fn (Event $event): bool => (bool) $event->getAttribute('before_entry_point'))
            ->map(fn (Event $event): int => (int) $event->id)
            ->values()
            ->all();
        $failures = [];
        $previous = null;

        foreach ($moments as $moment) {
            $momentKey = (string) $moment['moment_key'];
            $sourceIds = array_map('intval', (array) $moment['source_event_ids']);
            $staged = array_values(array_intersect($sourceIds, $preEntry));

            if ($staged !== []) {
                $failures[] = "Moment {$momentKey} stages events ".implode(', ', $staged).' from before the entry point. The cold open covers them: give them CUT, ECHO or MONTAGE with a reintroduction note instead.';
            }

            $earliest = min(array_map(fn (int $id): int => (int) ($positions[$id] ?? 0), $sourceIds));
            $anchor = (int) ($positions[(int) $moment['anchor_event_id']] ?? 0);

            $known = $earliest > 0 && $anchor > 0;

            if ($known && $previous !== null && ($earliest < $previous['earliest'] || $anchor <= $previous['anchor'])) {
                $failures[] = "Moment {$momentKey} goes back in the story before {$previous['key']} (its events start at story position {$earliest}, its anchor is {$anchor}). Cards must follow source order.";
            }

            if ($known) {
                $previous = ['key' => $momentKey, 'earliest' => $earliest, 'anchor' => $anchor];
            }
        }

        if ($failures !== []) {
            throw new RuntimeException("Boiler Draft story order:\n- ".implode("\n- ", $failures));
        }
    }

    /**
     * Scan all prose fields in the draft for voice-ban violations.
     * Catches em dashes, semicolons, trailing similes, and banned
     * vocabulary on the same pass as structural validation, so the
     * repair loop can fix them immediately rather than deferring to
     * Editorial Verification at the end of the pipeline.
     */
    private function validateVoiceBans(array $draft, SessionAdaptation $session): void
    {
        $validator = $this->bannedProseValidator ?? new BannedProseValidator;
        $voiceProfile = (array) ($session->storyAdaptation?->voice_profile ?? []);

        $scan = $validator->scan(
            $draft,
            $voiceProfile,
            $this->expandProsePaths($draft, self::PROSE_PATHS),
        );

        if ($scan['errors'] !== []) {
            throw new RuntimeException(
                "Boiler Draft voice-ban violations:\n- ".implode("\n- ", $scan['errors'])
            );
        }
    }

    /**
     * Expand wildcard paths (moment_drafts.*.runtime_goal) into concrete
     * paths (moment_drafts.0.runtime_goal, moment_drafts.1.runtime_goal, ...).
     *
     * @param  array<string, mixed>  $artifact
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private function expandProsePaths(array $artifact, array $paths): array
    {
        $expanded = [];

        foreach ($paths as $path) {
            if (! str_contains($path, '.*.')) {
                $expanded[] = $path;

                continue;
            }

            [$prefix, $suffix] = explode('.*.', $path, 2);
            $collection = data_get($artifact, $prefix);

            if (! is_array($collection)) {
                continue;
            }

            foreach (array_keys($collection) as $index) {
                $expanded[] = "{$prefix}.{$index}.{$suffix}";
            }
        }

        return $expanded;
    }
}
