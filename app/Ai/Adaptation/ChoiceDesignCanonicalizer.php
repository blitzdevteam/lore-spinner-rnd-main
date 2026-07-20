<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

final class ChoiceDesignCanonicalizer
{
    /**
     * Bind SESSION_END_HOOK to the final Draft moment.
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
        $choices = (array) ($choiceDesign['branching_choices'] ?? []);
        $finalDraftMoment = collect((array) ($draft['moment_drafts'] ?? []))
            ->sortBy('moment_order')
            ->last();
        $finalMomentKey = is_array($finalDraftMoment)
            ? ($finalDraftMoment['moment_key'] ?? null)
            : null;
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
