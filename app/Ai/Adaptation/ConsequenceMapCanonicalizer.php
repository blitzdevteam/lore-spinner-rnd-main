<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

final class ConsequenceMapCanonicalizer
{
    /**
     * Overlay identifiers and bindings owned by Phase 5 Choice Design.
     *
     * @param  array<string, mixed>  $consequenceMap
     * @param  array<string, mixed>  $choiceDesign
     * @return array<string, mixed>
     */
    public function canonicalize(array $consequenceMap, array $choiceDesign): array
    {
        $branchingChoices = collect((array) ($choiceDesign['branching_choices'] ?? []));
        $lastBranchingIndex = $branchingChoices->count() - 1;
        $branchingConsequences = collect((array) ($consequenceMap['branching_consequences'] ?? []))
            ->keyBy('choice_id');

        $consequenceMap['branching_consequences'] = $branchingChoices
            ->map(function ($choice, int $index) use ($branchingConsequences, $lastBranchingIndex): array {
                if (! is_array($choice)) {
                    return [];
                }

                $choiceId = (string) ($choice['choice_id'] ?? '');
                $consequence = (array) $branchingConsequences->get($choiceId, []);
                $paths = collect((array) ($consequence['paths'] ?? []))
                    ->keyBy(fn ($path): string => mb_strtoupper((string) ($path['label'] ?? '')));

                $consequence['choice_id'] = $choiceId;
                $consequence['moment_key'] = $choice['moment_key'] ?? '';
                $consequence['tracked_dimension'] = $choice['what_this_choice_tracks'] ?? '';
                $consequence['is_session_end_hook'] = ($choice['category'] ?? null) === 'SESSION_END_HOOK'
                    || $index === $lastBranchingIndex;
                $consequence['paths'] = collect((array) ($choice['options'] ?? []))
                    ->map(function ($option) use ($paths): array {
                        if (! is_array($option)) {
                            return [];
                        }

                        $label = mb_strtoupper((string) ($option['label'] ?? ''));
                        $path = (array) $paths->get($label, []);
                        $path['label'] = $label;
                        $path['alignment'] = mb_strtoupper((string) ($option['alignment'] ?? ''));
                        $path['defining_line_captured'] = $option['defining_line'] ?? '';

                        return $path;
                    })
                    ->values()
                    ->all();

                return $consequence;
            })
            ->values()
            ->all();

        $emotionalChoices = collect((array) ($choiceDesign['emotional_choices'] ?? []));
        $emotionalConsequences = collect((array) ($consequenceMap['emotional_consequences'] ?? []))
            ->keyBy('choice_id');

        $consequenceMap['emotional_consequences'] = $emotionalChoices
            ->map(function ($choice) use ($emotionalConsequences): array {
                if (! is_array($choice)) {
                    return [];
                }

                $choiceId = (string) ($choice['choice_id'] ?? '');
                $consequence = (array) $emotionalConsequences->get($choiceId, []);
                $paths = collect((array) ($consequence['paths'] ?? []))
                    ->keyBy(fn ($path): string => mb_strtoupper((string) ($path['label'] ?? '')));

                $consequence['choice_id'] = $choiceId;
                $consequence['moment_key'] = $choice['moment_key'] ?? '';
                $consequence['paths'] = collect((array) ($choice['options'] ?? []))
                    ->map(function ($option) use ($paths): array {
                        if (! is_array($option)) {
                            return [];
                        }

                        $label = mb_strtoupper((string) ($option['label'] ?? ''));
                        $path = (array) $paths->get($label, []);
                        $path['label'] = $label;
                        $path['alignment'] = mb_strtoupper((string) ($option['alignment'] ?? ''));

                        return $path;
                    })
                    ->values()
                    ->all();

                return $consequence;
            })
            ->values()
            ->all();

        return $consequenceMap;
    }
}
