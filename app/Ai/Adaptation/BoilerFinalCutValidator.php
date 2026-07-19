<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use App\Models\Event;
use App\Models\SessionAdaptation;
use Illuminate\Support\Collection;
use RuntimeException;

final class BoilerFinalCutValidator
{
    /**
     * @param  array<string, mixed>  $finalCut
     * @param  Collection<int, Event>  $events
     */
    public function validate(array $finalCut, SessionAdaptation $session, Collection $events): void
    {
        $draft = (array) $session->boiler_draft;
        $draftMoments = collect((array) ($draft['moment_drafts'] ?? []))
            ->sortBy('moment_order')
            ->values();
        $moments = $finalCut['moments'] ?? null;
        $targetCount = data_get($draft, 'session_budget_decision.target_moment_count');

        if (
            ($finalCut['boiler_version'] ?? null) !== '1.0'
            || ($finalCut['session_number'] ?? null) !== $session->session_number
            || ($finalCut['cut_key'] ?? null) !== 'standard'
        ) {
            throw new RuntimeException('Boiler Finalize returned an invalid version, session, or cut key.');
        }

        if (! is_array($moments) || ! is_int($targetCount) || count($moments) !== $targetCount) {
            throw new RuntimeException('Boiler Finalize moment count must equal the approved Draft target.');
        }

        if (data_get($session->session_architecture, 'boiler_budget_alignment.aligned') !== true) {
            throw new RuntimeException('Boiler Finalize cannot proceed with an unresolved Architecture budget conflict.');
        }

        $eventIds = $events->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $choiceDesign = (array) $session->session_choice_design;
        $authoredChoices = collect([
            ...(array) ($choiceDesign['branching_choices'] ?? []),
            ...(array) ($choiceDesign['emotional_choices'] ?? []),
        ])->keyBy('choice_id');

        $finalMomentKeys = [];
        foreach ($moments as $index => $card) {
            if (! is_array($card)) {
                throw new RuntimeException('Every finalized Boiler moment must be an object.');
            }

            $expectedOrder = $index + 1;
            $draftMoment = $draftMoments->get($index);
            $momentKey = $card['moment_key'] ?? null;

            if (
                ! is_array($draftMoment)
                || $momentKey !== ($draftMoment['moment_key'] ?? null)
                || ($card['moment_order'] ?? null) !== $expectedOrder
            ) {
                throw new RuntimeException('Boiler Finalize must preserve Draft moment keys and sequential order.');
            }

            if (
                (array) ($card['source_event_ids'] ?? []) !== (array) ($draftMoment['source_event_ids'] ?? [])
                || ($card['anchor_event_id'] ?? null) !== ($draftMoment['anchor_event_id'] ?? null)
            ) {
                throw new RuntimeException("Final scene card {$momentKey} changed Draft source or anchor events.");
            }

            foreach ((array) ($card['source_event_ids'] ?? []) as $eventId) {
                if (! is_int($eventId) || ! in_array($eventId, $eventIds, true)) {
                    throw new RuntimeException("Final scene card {$momentKey} references an out-of-session event.");
                }
            }

            foreach (['title', 'runtime_goal', 'player_desire', 'live_pressure', 'opening_image', 'next_bridge'] as $field) {
                if (! is_string($card[$field] ?? null) || trim($card[$field]) === '') {
                    throw new RuntimeException("Final scene card {$momentKey} requires {$field}.");
                }
            }

            foreach (['player_affordances', 'canon_must_keep', 'allowed_variance', 'forbidden_expansions', 'source_extracts', 'choice_ids'] as $field) {
                if (! is_array($card[$field] ?? null)) {
                    throw new RuntimeException("Final scene card {$momentKey} requires an array {$field}.");
                }
            }

            if ($card['player_affordances'] === [] || $card['source_extracts'] === []) {
                throw new RuntimeException("Final scene card {$momentKey} requires affordances and local source extracts.");
            }

            $exitCondition = $card['exit_condition'] ?? null;
            if (! is_string($exitCondition) || mb_strlen(trim($exitCondition)) < 30 || mb_strlen($exitCondition) > 1_000) {
                throw new RuntimeException("Final scene card {$momentKey} requires a bounded semantic exit condition.");
            }

            $targetWords = data_get($card, 'word_budget.target_per_turn');
            $targetTurns = data_get($card, 'turn_budget.target_turns');
            $gravityAfter = data_get($card, 'turn_budget.gravity_after');
            if (
                ! is_int($targetWords) || $targetWords < 1
                || ! is_int($targetTurns) || $targetTurns < 1
                || ! is_int($gravityAfter) || $gravityAfter < $targetTurns
            ) {
                throw new RuntimeException("Final scene card {$momentKey} has invalid word or turn budgets.");
            }

            foreach ($card['choice_ids'] as $choiceId) {
                $choice = $authoredChoices->get($choiceId);
                if (! is_array($choice) || ($choice['moment_key'] ?? null) !== $momentKey) {
                    throw new RuntimeException("Final scene card {$momentKey} references an invalid or differently bound choice.");
                }
            }

            $expectedNextKey = $index < count($moments) - 1
                ? ($moments[$index + 1]['moment_key'] ?? null)
                : null;
            $actualNextKey = ($card['next_moment_key'] ?? '') !== ''
                ? $card['next_moment_key']
                : null;

            if ($actualNextKey !== $expectedNextKey) {
                throw new RuntimeException("Final scene card {$momentKey} has an invalid next_moment_key.");
            }

            $finalMomentKeys[] = $momentKey;
        }

        $finalKey = end($finalMomentKeys);
        $arc = (array) ($draft['playable_promise_arc'] ?? []);
        foreach (['first_proof_moment', 'midpoint_intensification', 'final_cash_out'] as $field) {
            if (! in_array($arc[$field] ?? null, $finalMomentKeys, true)) {
                throw new RuntimeException("Final cut does not resolve playable_promise_arc.{$field}.");
            }
        }
        if (
            ($arc['final_cash_out'] ?? null) !== $finalKey
            || data_get($session->session_close_design, 'closing_moment_key') !== $finalKey
        ) {
            throw new RuntimeException('Final cut, playable promise cash-out, and Session Close must share the final moment.');
        }

        $draftRoles = collect((array) ($draft['event_role_map'] ?? []))->keyBy('event_id');
        $finalRoles = $finalCut['event_role_map'] ?? null;
        if (! is_array($finalRoles) || count($finalRoles) !== count($eventIds)) {
            throw new RuntimeException('Final event-role coverage must include every assigned event exactly once.');
        }

        $seenRoleEventIds = [];
        foreach ($finalRoles as $role) {
            if (! is_array($role) || ! is_int($role['event_id'] ?? null)) {
                throw new RuntimeException('Final event roles require integer event IDs.');
            }
            $eventId = $role['event_id'];
            $draftRole = $draftRoles->get($eventId);
            if (
                ! is_array($draftRole)
                || in_array($eventId, $seenRoleEventIds, true)
                || ($role['primary_role'] ?? null) !== ($draftRole['role'] ?? null)
                || ($role['included_moment_key'] ?? '') !== ($draftRole['included_moment_key'] ?? '')
                || ($role['why'] ?? null) !== ($draftRole['why'] ?? null)
                || ($role['reintroduction_note'] ?? '') !== ($draftRole['reintroduction_note'] ?? '')
            ) {
                throw new RuntimeException('Final event roles changed, duplicated, or omitted Draft coverage or rationale.');
            }
            $seenRoleEventIds[] = $eventId;
        }

        sort($seenRoleEventIds);
        sort($eventIds);
        if ($seenRoleEventIds !== $eventIds) {
            throw new RuntimeException('Final event-role coverage does not match the assigned session events.');
        }
    }
}
