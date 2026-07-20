<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

final class SessionArchitectureCanonicalizer
{
    /**
     * Reserve the final Architecture branching slot for SESSION_END_HOOK.
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
        $finalMomentKey = $draftMoments->last()['moment_key'] ?? null;
        $beatMap = (array) ($architecture['beat_map'] ?? []);
        $branchingIndexes = collect($beatMap)
            ->filter(fn ($beat): bool => is_array($beat)
                && str_starts_with(
                    mb_strtoupper(mb_trim((string) ($beat['choice_slot'] ?? ''))),
                    'BRANCHING',
                ))
            ->keys();
        $finalBranchIndex = $branchingIndexes
            ->first(fn ($index): bool => is_array($beatMap[$index] ?? null)
                && ($beatMap[$index]['moment_key'] ?? null) === $finalMomentKey)
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

        $architecture['beat_map'] = $beatMap;

        return $architecture;
    }
}
