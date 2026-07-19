<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use RuntimeException;

final class SessionArchitectureValidator
{
    /**
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

        $requiredChoiceMoments = collect((array) ($draft['event_role_map'] ?? []))
            ->filter(fn ($role): bool => is_array($role)
                && ($role['role'] ?? null) === 'CHOICE'
                && is_string($role['included_moment_key'] ?? null)
                && $role['included_moment_key'] !== '')
            ->pluck('included_moment_key')
            ->unique()
            ->values();
        $branchingMoments = collect();
        $breathWindows = [];
        $branchingSlots = 0;
        foreach ((array) ($architecture['beat_map'] ?? []) as $beat) {
            if (! is_array($beat) || ! in_array($beat['moment_key'] ?? null, $momentKeys, true)) {
                throw new RuntimeException('Every Boiler-enabled beat-map row must reference a valid draft moment_key.');
            }

            [$startMinute, $endMinute] = $this->parseTimeRange((string) ($beat['time_range'] ?? ''));
            if ($endMinute <= $startMinute) {
                throw new RuntimeException('Every Architecture time_range must end after it starts.');
            }

            if (str_starts_with(
                mb_strtoupper(mb_trim((string) ($beat['choice_slot'] ?? ''))),
                'BRANCHING',
            )) {
                $branchingSlots++;
                $branchingMoments->push($beat['moment_key']);
            }

            if (mb_strtoupper(mb_trim((string) ($beat['beat_type'] ?? ''))) === 'BREATH') {
                $breathWindows[] = [
                    'range' => (string) $beat['time_range'],
                    'start' => $startMinute,
                    'end' => $endMinute,
                ];
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

        $missingChoiceMoments = $requiredChoiceMoments
            ->diff($branchingMoments->unique())
            ->values();
        if ($missingChoiceMoments->isNotEmpty()) {
            throw new RuntimeException(
                'Every Draft CHOICE-role moment requires a BRANCHING Architecture slot; missing: '
                .$missingChoiceMoments->implode(', ').'.'
            );
        }

        if (count($breathWindows) !== 1) {
            throw new RuntimeException('Boiler-enabled Session Architecture requires exactly one BREATH beat-map row.');
        }

        $breath = $breathWindows[0];
        if ($breath['start'] < 10 || $breath['end'] > 13) {
            throw new RuntimeException(
                'The BREATH beat must fit wholly inside minutes 10:00-13:00; '
                ."received {$breath['range']}."
            );
        }
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function parseTimeRange(string $range): array
    {
        if (! preg_match(
            '/^\s*(\d{1,2}):([0-5]\d)\s*[-–—]\s*(\d{1,2}):([0-5]\d)\s*$/u',
            $range,
            $matches,
        )) {
            throw new RuntimeException(
                "Architecture time_range must use MM:SS-MM:SS format; received {$range}."
            );
        }

        return [
            (int) $matches[1] + ((int) $matches[2] / 60),
            (int) $matches[3] + ((int) $matches[4] / 60),
        ];
    }
}
