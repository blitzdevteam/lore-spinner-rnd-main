<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use Illuminate\Support\Collection;

final class SessionArchitectureCanonicalizer
{
    private const int CANONICAL_BRANCHING_SLOTS = 4;

    /**
     * Make the Architecture's structural obligations deterministic.
     *
     * Phase 4 still owns judgment — which beats carry pressure, how time is
     * distributed, where the breath lands. But the mechanical contract (exactly four
     * BRANCHING slots, the session-end hook on the final Draft moment, and whether
     * the result actually fits the Draft) is decided here in PHP, because those are
     * facts about the artifact rather than opinions about it.
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
            ->values();
        $finalMomentKey = $draftMoments->last()['moment_key'] ?? null;
        $beatMap = (array) ($architecture['beat_map'] ?? []);

        $finalBranchIndex = $this->branchingIndexes($beatMap)
            ->first(fn ($index): bool => is_array($beatMap[$index] ?? null)
                && ($beatMap[$index]['moment_key'] ?? null) === $finalMomentKey)
            ?? $this->branchingIndexes($beatMap)->last();

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

        $beatMap = $this->enforceBranchingSlotCount($beatMap, $finalBranchIndex);

        $architecture['beat_map'] = $beatMap;
        $architecture['boiler_budget_alignment'] = $this->resolveAlignment(
            $architecture['boiler_budget_alignment'] ?? null,
            $beatMap,
            $momentKeys->all(),
            is_string($finalMomentKey) ? $finalMomentKey : null,
        );

        return $architecture;
    }

    /**
     * @param  array<int, mixed>  $beatMap
     * @return Collection<int, int>
     */
    private function branchingIndexes(array $beatMap): Collection
    {
        return collect($beatMap)
            ->filter(fn ($beat): bool => is_array($beat) && $this->isBranching($beat))
            ->keys();
    }

    /**
     * @param  array<string, mixed>  $beat
     */
    private function isBranching(array $beat): bool
    {
        return str_starts_with(
            mb_strtoupper(mb_trim((string) ($beat['choice_slot'] ?? ''))),
            'BRANCHING',
        );
    }

    /**
     * Force the beat map to carry exactly four BRANCHING slots.
     *
     * A short bridge session can have fewer moments than canonical branches, so
     * slots are allowed to share a moment. Spreading them across distinct moments is
     * preferred, but running out of moments must never make the contract
     * unsatisfiable.
     *
     * @param  array<int, mixed>  $beatMap
     * @return array<int, mixed>
     */
    private function enforceBranchingSlotCount(array $beatMap, int|string|null $finalBranchIndex): array
    {
        $branching = $this->branchingIndexes($beatMap);

        if ($branching->count() > self::CANONICAL_BRANCHING_SLOTS) {
            // Keep the session-end hook plus the earliest slots; demote the surplus.
            $keep = collect([$finalBranchIndex])
                ->filter(fn ($index): bool => $index !== null)
                ->merge($branching->reject(fn ($index): bool => $index === $finalBranchIndex))
                ->take(self::CANONICAL_BRANCHING_SLOTS)
                ->all();

            foreach ($branching as $index) {
                if (! in_array($index, $keep, true)) {
                    $beatMap[$index]['choice_slot'] = 'none';
                }
            }

            return $beatMap;
        }

        $missing = self::CANONICAL_BRANCHING_SLOTS - $branching->count();
        if ($missing <= 0) {
            return $beatMap;
        }

        $claimedMoments = $branching
            ->map(fn ($index) => $beatMap[$index]['moment_key'] ?? null)
            ->filter()
            ->all();

        // First pass prefers beats on moments that do not yet own a branch, so the
        // slots spread out whenever the session is long enough to allow it.
        foreach ([true, false] as $preferUnclaimedMoments) {
            foreach ($beatMap as $index => $beat) {
                if ($missing <= 0) {
                    break 2;
                }
                if (! is_array($beat) || $this->isBranching($beat)) {
                    continue;
                }

                $momentKey = $beat['moment_key'] ?? null;
                if ($preferUnclaimedMoments && in_array($momentKey, $claimedMoments, true)) {
                    continue;
                }

                $beatMap[$index]['choice_slot'] = 'BRANCHING';
                $claimedMoments[] = $momentKey;
                $missing--;
            }
        }

        return $beatMap;
    }

    /**
     * Decide budget alignment from the artifact instead of the model's self-report.
     *
     * `aligned` is a structural claim — does the beat map stay inside the Draft's
     * moments without adding, dropping, or relocating any. PHP can answer that with
     * certainty, and doing so stops a hedged or self-contradictory model verdict from
     * failing a session whose architecture is in fact correct.
     *
     * @param  array<int, mixed>  $beatMap
     * @param  array<int, string>  $momentKeys
     * @return array{aligned: bool, conflict: string}
     */
    private function resolveAlignment(
        mixed $reported,
        array $beatMap,
        array $momentKeys,
        ?string $finalMomentKey,
    ): array {
        $conflicts = [];

        if ($momentKeys === []) {
            $conflicts[] = 'the Boiler Draft supplied no moments';
        }

        foreach ($beatMap as $beat) {
            if (! is_array($beat)) {
                $conflicts[] = 'a beat-map row is not an object';

                continue;
            }

            $momentKey = $beat['moment_key'] ?? null;
            if (! is_string($momentKey) || ! in_array($momentKey, $momentKeys, true)) {
                $conflicts[] = 'a beat-map row does not reference a Draft moment_key';
            }
        }

        $branchingCount = $this->branchingIndexes($beatMap)->count();
        if ($branchingCount !== self::CANONICAL_BRANCHING_SLOTS) {
            $conflicts[] = "beat map carries {$branchingCount} BRANCHING slots instead of ".self::CANONICAL_BRANCHING_SLOTS;
        }

        $finalOwnsBranch = $this->branchingIndexes($beatMap)
            ->contains(fn ($index): bool => ($beatMap[$index]['moment_key'] ?? null) === $finalMomentKey);
        if ($finalMomentKey !== null && ! $finalOwnsBranch) {
            $conflicts[] = 'the final Draft moment does not own a BRANCHING slot';
        }

        if ($conflicts === []) {
            return ['aligned' => true, 'conflict' => ''];
        }

        // Prefer the model's own explanation when it also reported a conflict.
        $reportedConflict = is_array($reported) ? mb_trim((string) ($reported['conflict'] ?? '')) : '';
        $reportedAligned = is_array($reported) && ($reported['aligned'] ?? null) === true;

        return [
            'aligned' => false,
            'conflict' => ($reportedConflict !== '' && ! $reportedAligned)
                ? $reportedConflict
                : implode('; ', $conflicts),
        ];
    }
}
