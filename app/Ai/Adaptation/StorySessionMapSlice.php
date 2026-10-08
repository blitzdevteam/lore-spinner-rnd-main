<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

/**
 * The Story Session Map as one session's phases need it.
 *
 * Story-wide sections (branch dimensions, state schema, reactivity rules,
 * canon, alignment labels) stay whole. Per-session lists keep this session and
 * its neighbours, plus every cross-session payoff that touches this session,
 * so a phase is not handed other sessions' plans to borrow from.
 */
final class StorySessionMapSlice
{
    /**
     * @param  array<string, mixed>  $map
     * @param  list<string>  $without  Sections the prompt already supplies on their own.
     * @return array<string, mixed>
     */
    public static function forSession(array $map, int $sessionNumber, array $without = []): array
    {
        $near = static fn (mixed $entry): bool => abs((int) data_get($entry, 'session_number', 0) - $sessionNumber) <= 1;

        foreach (['session_allocation', 'arc_progression'] as $section) {
            if (is_array($map[$section] ?? null)) {
                $map[$section] = array_values(array_filter($map[$section], $near));
            }
        }

        if (is_array($map['branch_opportunities'] ?? null)) {
            $map['branch_opportunities'] = array_values(array_filter(
                $map['branch_opportunities'],
                static fn (mixed $entry): bool => (int) data_get($entry, 'session_number', 0) === $sessionNumber
                    || (int) data_get($entry, 'downstream_payoff_session', 0) === $sessionNumber,
            ));
        }

        if (is_array($map['cross_session_payoff_plan'] ?? null)) {
            $map['cross_session_payoff_plan'] = array_values(array_filter(
                $map['cross_session_payoff_plan'],
                static fn (mixed $entry): bool => (int) data_get($entry, 'echo_session', 0) === $sessionNumber
                    || (int) data_get($entry, 'payoff_session', 0) === $sessionNumber
                    || preg_match('/\bsession\s*'.$sessionNumber.'\b|\bS'.$sessionNumber.'_/i', (string) data_get($entry, 'choice_reference', '')) === 1,
            ));
        }

        if (is_array(data_get($map, 'boiling_profile.per_session_guidance'))) {
            $map['boiling_profile']['per_session_guidance'] = array_values(array_filter(
                $map['boiling_profile']['per_session_guidance'],
                static fn (mixed $entry): bool => (int) data_get($entry, 'session_number', 0) === $sessionNumber,
            ));
        }

        return array_diff_key($map, array_flip($without));
    }
}
