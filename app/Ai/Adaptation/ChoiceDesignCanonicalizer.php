<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

final class ChoiceDesignCanonicalizer
{
    /**
     * Bind Boiler moment_keys from the Draft so Choice Design can persist like
     * other pipeline jobs after a single LLM call.
     *
     * Other canonical branching placements remain authored by Phases 4 and 5.
     * Draft CHOICE roles are pressure candidates, not extra branch identities.
     *
     * @param  array<string, mixed>  $choiceDesign
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function canonicalize(array $choiceDesign, array $draft): array
    {
        $draftMoments = collect((array) ($draft['moment_drafts'] ?? []))
            ->sortBy('moment_order')
            ->values();
        $momentKeys = $draftMoments
            ->pluck('moment_key')
            ->filter(fn ($key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();
        $firstMomentKey = $momentKeys[0] ?? null;
        $lastDraftMoment = $draftMoments->last();
        $finalMomentKey = is_array($lastDraftMoment)
            ? ($lastDraftMoment['moment_key'] ?? null)
            : null;

        foreach (['branching_choices', 'emotional_choices', 'posture_shifts'] as $collection) {
            $entries = (array) ($choiceDesign[$collection] ?? []);
            foreach ($entries as $index => $choice) {
                if (! is_array($choice)) {
                    continue;
                }

                if (! in_array($choice['moment_key'] ?? null, $momentKeys, true)) {
                    $entries[$index]['moment_key'] = $firstMomentKey;
                }

                if (
                    ($choice['category'] ?? null) === 'SESSION_END_HOOK'
                    && is_string($finalMomentKey)
                    && $finalMomentKey !== ''
                ) {
                    $entries[$index]['moment_key'] = $finalMomentKey;
                }
            }
            $choiceDesign[$collection] = $entries;
        }

        $choices = (array) ($choiceDesign['branching_choices'] ?? []);
        $sessionEndIndex = collect($choices)->search(
            fn ($choice): bool => is_array($choice)
                && ($choice['category'] ?? null) === 'SESSION_END_HOOK',
        );
        if (
            $sessionEndIndex !== false
            && is_string($finalMomentKey)
            && $finalMomentKey !== ''
            && is_array($choices[$sessionEndIndex] ?? null)
        ) {
            $choices[$sessionEndIndex]['moment_key'] = $finalMomentKey;
        }

        $choiceDesign['branching_choices'] = $choices;

        return $choiceDesign;
    }
}
