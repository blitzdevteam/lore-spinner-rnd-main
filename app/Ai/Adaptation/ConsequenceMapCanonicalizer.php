<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

final class ConsequenceMapCanonicalizer
{
    /**
     * Overlay identifiers and bindings owned by Phase 5 Choice Design.
     * PHP repairs structure so the phase can persist after one LLM call.
     *
     * @param  array<string, mixed>  $consequenceMap
     * @param  array<string, mixed>  $choiceDesign
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function canonicalize(array $consequenceMap, array $choiceDesign, array $draft = []): array
    {
        $momentKeys = collect((array) ($draft['moment_drafts'] ?? []))
            ->pluck('moment_key')
            ->filter(fn ($key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();

        $branchingChoices = collect((array) ($choiceDesign['branching_choices'] ?? []));
        $lastBranchingIndex = $branchingChoices->count() - 1;
        $branchingConsequences = collect((array) ($consequenceMap['branching_consequences'] ?? []))
            ->keyBy('choice_id');
        $originalFreeform = (array) ($consequenceMap['freeform_guidelines'] ?? []);

        $consequenceMap['branching_consequences'] = $branchingChoices
            ->map(function ($choice, int $index) use ($branchingConsequences, $lastBranchingIndex, $momentKeys): array {
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
                if (mb_trim((string) ($consequence['moment_narrator_hook'] ?? '')) === '') {
                    $consequence['moment_narrator_hook'] = 'Surface the chosen path inside the active moment.';
                }

                $echo = $consequence['echo_moment_key'] ?? null;
                if (
                    $echo !== null
                    && $echo !== ''
                    && $momentKeys !== []
                    && ! in_array($echo, $momentKeys, true)
                ) {
                    $consequence['echo_moment_key'] = '';
                }

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
            ->map(function ($choice) use ($emotionalConsequences, $momentKeys): array {
                if (! is_array($choice)) {
                    return [];
                }

                $choiceId = (string) ($choice['choice_id'] ?? '');
                $consequence = (array) $emotionalConsequences->get($choiceId, []);
                $paths = collect((array) ($consequence['paths'] ?? []))
                    ->keyBy(fn ($path): string => mb_strtoupper((string) ($path['label'] ?? '')));

                $consequence['choice_id'] = $choiceId;
                $consequence['moment_key'] = $choice['moment_key'] ?? '';
                if (mb_trim((string) ($consequence['moment_narrator_hook'] ?? '')) === '') {
                    $consequence['moment_narrator_hook'] = 'Surface the emotional pressure inside the active moment.';
                }

                $echo = $consequence['echo_moment_key'] ?? null;
                if (
                    $echo !== null
                    && $echo !== ''
                    && $momentKeys !== []
                    && ! in_array($echo, $momentKeys, true)
                ) {
                    $consequence['echo_moment_key'] = '';
                }

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

        // Freeform pairs are Phase 5 identity — rebuild exactly rather than reject the map.
        $consequenceMap['freeform_guidelines'] = $branchingChoices
            ->flatMap(function ($choice) use ($originalFreeform): array {
                if (! is_array($choice)) {
                    return [];
                }

                $choiceId = (string) ($choice['choice_id'] ?? '');
                $existing = collect($originalFreeform)
                    ->filter(fn ($guideline): bool => is_array($guideline)
                        && ($guideline['choice_id'] ?? null) === $choiceId)
                    ->keyBy(fn ($guideline): string => mb_strtoupper((string) ($guideline['path_label'] ?? '')));

                return collect((array) ($choice['options'] ?? []))
                    ->map(function ($option) use ($choiceId, $existing): array {
                        $label = mb_strtoupper((string) ($option['label'] ?? ''));
                        $guideline = (array) $existing->get($label, []);
                        $guideline['choice_id'] = $choiceId;
                        $guideline['path_label'] = $label;

                        return $guideline;
                    })
                    ->all();
            })
            ->values()
            ->all();

        return $consequenceMap;
    }
}
