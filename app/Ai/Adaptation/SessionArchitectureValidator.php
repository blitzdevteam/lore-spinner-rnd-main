<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use RuntimeException;

final class SessionArchitectureValidator
{
    /**
     * Enforce only the universal structural invariants every Boiler story must satisfy.
     *
     * Pacing checks (BREATH window, time-range format) are intentionally left to
     * Editorial Verification Q7 — they are story-dependent guidelines, not hard
     * structural contracts that hold across all IPs.
     *
     * @param  array<string, mixed>  $architecture
     * @param  array<string, mixed>  $draft
     */
    public function validate(array $architecture, array $draft): void
    {
        $momentKeys = collect((array) ($draft['moment_drafts'] ?? []))
            ->pluck('moment_key')
            ->filter(fn ($key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();

        if ($momentKeys === []) {
            throw new RuntimeException('Boiler-enabled Session Architecture requires a validated Boiler Draft.');
        }

        $alignment = $architecture['boiler_budget_alignment'] ?? null;
        if (! is_array($alignment) || ($alignment['aligned'] ?? null) !== true) {
            $conflict = is_array($alignment)
                ? ($alignment['conflict'] ?? 'unspecified conflict')
                : 'missing alignment result';

            throw new RuntimeException("Session Architecture conflicts with the approved Boiler budget: {$conflict}");
        }

        $branchingMoments = collect();
        $branchingSlots = 0;

        foreach ((array) ($architecture['beat_map'] ?? []) as $beat) {
            if (! is_array($beat) || ! in_array($beat['moment_key'] ?? null, $momentKeys, true)) {
                throw new RuntimeException('Every Boiler-enabled beat-map row must reference a valid draft moment_key.');
            }

            if (str_starts_with(
                mb_strtoupper(mb_trim((string) ($beat['choice_slot'] ?? ''))),
                'BRANCHING',
            )) {
                $branchingSlots++;
                $branchingMoments->push($beat['moment_key']);
            }
        }

        if ($branchingSlots !== 4) {
            throw new RuntimeException('Boiler-enabled Session Architecture requires exactly four BRANCHING beat-map slots.');
        }

        $finalMomentKey = end($momentKeys);
        if (! is_string($finalMomentKey) || ! $branchingMoments->contains($finalMomentKey)) {
            throw new RuntimeException(
                'The final Draft moment must own a BRANCHING Architecture slot for the session-end hook.'
            );
        }
    }
}
