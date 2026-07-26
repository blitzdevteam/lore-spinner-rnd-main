<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

final class SessionArchitectureCanonicalizer
{
    /**
     * Overlay Draft-owned identity onto Architecture so Boiler mode can persist
     * like other pipeline jobs: one LLM call, PHP repairs structure, no repair loop.
     *
     * Draft CHOICE roles identify candidate pressure; they do not create extra
     * canonical branches. Phase 4 still owns placement of the other three slots.
     *
     * @param  array<string, mixed>  $architecture
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function canonicalize(array $architecture, array $draft): array
    {
        $draftMoments = collect((array) ($draft['moment_drafts'] ?? []))
            ->sortBy('moment_order')
            ->values();
        $draftMomentsByKey = $draftMoments->keyBy('moment_key');
        $momentKeys = $draftMoments
            ->pluck('moment_key')
            ->filter(fn ($key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();
        $firstMomentKey = $momentKeys[0] ?? null;
        $finalMomentKey = $draftMoments->last()['moment_key'] ?? null;

        // Draft already owns the approved budget — never fail the phase on LLM alignment prose.
        $alignment = (array) ($architecture['boiler_budget_alignment'] ?? []);
        $architecture['boiler_budget_alignment'] = array_merge($alignment, [
            'aligned' => true,
            'conflict' => (string) ($alignment['conflict'] ?? ''),
        ]);

        $beatMap = array_values(array_filter(
            (array) ($architecture['beat_map'] ?? []),
            fn ($beat): bool => is_array($beat),
        ));

        foreach ($beatMap as $index => $beat) {
            if (! in_array($beat['moment_key'] ?? null, $momentKeys, true)) {
                $beatMap[$index]['moment_key'] = $firstMomentKey;
            }
        }

        $isBranching = static fn (array $beat): bool => str_starts_with(
            mb_strtoupper(mb_trim((string) ($beat['choice_slot'] ?? ''))),
            'BRANCHING',
        );

        $branchingIndexes = collect($beatMap)
            ->filter(fn ($beat): bool => is_array($beat) && $isBranching($beat))
            ->keys()
            ->values();

        // Promote earliest non-branching beats until we have four BRANCHING slots.
        while ($branchingIndexes->count() < 4) {
            $promoteIndex = collect($beatMap)
                ->keys()
                ->first(fn ($index): bool => ! $branchingIndexes->contains($index));

            if ($promoteIndex === null) {
                $beatMap[] = [
                    'time_range' => '00:00-00:00',
                    'beat_type' => 'SETUP',
                    'choice_slot' => 'BRANCHING',
                    'moment_key' => $firstMomentKey,
                    'moment' => (string) ($draftMomentsByKey->get($firstMomentKey)['runtime_goal'] ?? $firstMomentKey),
                    'dramatic_function' => 'Canonical branching slot restored by PHP.',
                ];
                $branchingIndexes->push(count($beatMap) - 1);
                continue;
            }

            $beatMap[$promoteIndex]['choice_slot'] = 'BRANCHING';
            $branchingIndexes->push($promoteIndex);
            $branchingIndexes = $branchingIndexes->sort()->values();
        }

        // Demote surplus BRANCHING slots, keeping the final-moment owner when possible.
        while ($branchingIndexes->count() > 4) {
            $finalOwnedIndex = $branchingIndexes->first(
                fn ($index): bool => ($beatMap[$index]['moment_key'] ?? null) === $finalMomentKey
            );
            $demoteIndex = $branchingIndexes->first(
                fn ($index): bool => $index !== $finalOwnedIndex
            );
            if ($demoteIndex === null) {
                break;
            }
            $beatMap[$demoteIndex]['choice_slot'] = 'NONE';
            $branchingIndexes = $branchingIndexes->reject(fn ($index): bool => $index === $demoteIndex)->values();
        }

        $finalBranchIndex = $branchingIndexes
            ->first(fn ($index): bool => ($beatMap[$index]['moment_key'] ?? null) === $finalMomentKey)
            ?? $branchingIndexes->last();

        if (
            is_string($finalMomentKey)
            && $finalMomentKey !== ''
            && $finalBranchIndex !== null
            && is_array($beatMap[$finalBranchIndex] ?? null)
        ) {
            $finalDraftMoment = $draftMomentsByKey->get($finalMomentKey);
            $beatMap[$finalBranchIndex]['moment_key'] = $finalMomentKey;
            if (is_array($finalDraftMoment)) {
                $beatMap[$finalBranchIndex]['moment'] = (string) ($finalDraftMoment['runtime_goal'] ?? $finalMomentKey);
            }
            $beatMap[$finalBranchIndex]['dramatic_function'] = mb_trim(
                (string) ($beatMap[$finalBranchIndex]['dramatic_function'] ?? '')
                .' Session-end hook authority: the fourth canonical branch closes in the final Draft moment.'
            );
        }

        $architecture['beat_map'] = array_values($beatMap);

        return $architecture;
    }
}
