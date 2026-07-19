<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use RuntimeException;

final class ConsequenceMapValidator
{
    /**
     * @param  array<string, mixed>  $consequenceMap
     * @param  array<string, mixed>  $choiceDesign
     * @param  array<string, mixed>  $draft
     */
    public function validate(array $consequenceMap, array $choiceDesign, array $draft): void
    {
        $momentKeys = collect((array) ($draft['moment_drafts'] ?? []))
            ->pluck('moment_key')
            ->filter(fn ($key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();
        $branchingChoices = collect((array) ($choiceDesign['branching_choices'] ?? []))->values();
        $branchingConsequences = collect((array) ($consequenceMap['branching_consequences'] ?? []))->values();
        $emotionalChoices = collect((array) ($choiceDesign['emotional_choices'] ?? []))->values();
        $emotionalConsequences = collect((array) ($consequenceMap['emotional_consequences'] ?? []))->values();

        if ($branchingConsequences->count() !== $branchingChoices->count()) {
            throw new RuntimeException('Phase 6 must contain exactly one consequence for every Phase 5 branching choice.');
        }

        foreach ($branchingChoices as $index => $choice) {
            $consequence = $branchingConsequences->get($index);
            if (! is_array($choice) || ! is_array($consequence)) {
                throw new RuntimeException('Invalid branching choice or consequence entry.');
            }

            $choiceId = (string) ($choice['choice_id'] ?? '');
            $momentKey = $choice['moment_key'] ?? null;
            $expectedSessionEnd = ($choice['category'] ?? null) === 'SESSION_END_HOOK'
                || $index === $branchingChoices->count() - 1;

            if (
                ($consequence['choice_id'] ?? null) !== $choiceId
                || ! in_array($momentKey, $momentKeys, true)
                || ($consequence['moment_key'] ?? null) !== $momentKey
                || ($consequence['tracked_dimension'] ?? null) !== ($choice['what_this_choice_tracks'] ?? null)
                || ($consequence['is_session_end_hook'] ?? null) !== $expectedSessionEnd
            ) {
                throw new RuntimeException(
                    "Phase 6 branching consequence {$choiceId} changed its Phase 5 identity, dimension, or moment binding."
                );
            }

            $this->validatePaths(
                $choiceId,
                (array) ($consequence['paths'] ?? []),
                (array) ($choice['options'] ?? []),
                true,
            );
            $this->validateMomentHookAndEcho($choiceId, $consequence, $momentKeys);
        }

        if ($emotionalConsequences->count() !== $emotionalChoices->count()) {
            throw new RuntimeException('Phase 6 must contain exactly one consequence for every Phase 5 emotional choice.');
        }

        foreach ($emotionalChoices as $index => $choice) {
            $consequence = $emotionalConsequences->get($index);
            if (! is_array($choice) || ! is_array($consequence)) {
                throw new RuntimeException('Invalid emotional choice or consequence entry.');
            }

            $choiceId = (string) ($choice['choice_id'] ?? '');
            $momentKey = $choice['moment_key'] ?? null;
            if (
                ($consequence['choice_id'] ?? null) !== $choiceId
                || ! in_array($momentKey, $momentKeys, true)
                || ($consequence['moment_key'] ?? null) !== $momentKey
            ) {
                throw new RuntimeException(
                    "Phase 6 emotional consequence {$choiceId} changed its Phase 5 identity or moment binding."
                );
            }

            $this->validatePaths(
                $choiceId,
                (array) ($consequence['paths'] ?? []),
                (array) ($choice['options'] ?? []),
                false,
            );
            $this->validateMomentHookAndEcho($choiceId, $consequence, $momentKeys);
        }

        $expectedFreeformPairs = $branchingChoices
            ->flatMap(fn ($choice): array => collect((array) ($choice['options'] ?? []))
                ->map(fn ($option): string => ($choice['choice_id'] ?? '')
                    .'|'.mb_strtoupper((string) ($option['label'] ?? '')))
                ->all())
            ->sort()
            ->values()
            ->all();
        $actualFreeformPairs = collect((array) ($consequenceMap['freeform_guidelines'] ?? []))
            ->map(fn ($guideline): string => is_array($guideline)
                ? ($guideline['choice_id'] ?? '').'|'.mb_strtoupper((string) ($guideline['path_label'] ?? ''))
                : '')
            ->sort()
            ->values()
            ->all();

        if ($actualFreeformPairs !== $expectedFreeformPairs) {
            throw new RuntimeException('Phase 6 freeform guidelines must preserve every Phase 5 branching choice/path pair exactly once.');
        }
    }

    /**
     * @param  array<int, mixed>  $paths
     * @param  array<int, mixed>  $options
     */
    private function validatePaths(string $choiceId, array $paths, array $options, bool $requireDefiningLine): void
    {
        if (count($paths) !== count($options)) {
            throw new RuntimeException("Phase 6 consequence {$choiceId} changed its Phase 5 path count.");
        }

        foreach ($options as $index => $option) {
            $path = $paths[$index] ?? null;
            if (! is_array($option) || ! is_array($path)) {
                throw new RuntimeException("Phase 6 consequence {$choiceId} has an invalid path.");
            }

            if (
                ($path['label'] ?? null) !== mb_strtoupper((string) ($option['label'] ?? ''))
                || ($path['alignment'] ?? null) !== mb_strtoupper((string) ($option['alignment'] ?? ''))
                || ($requireDefiningLine
                    && ($path['defining_line_captured'] ?? null) !== ($option['defining_line'] ?? null))
            ) {
                throw new RuntimeException(
                    "Phase 6 consequence {$choiceId} changed a Phase 5 path label, alignment, or defining line."
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $consequence
     * @param  array<int, string>  $momentKeys
     */
    private function validateMomentHookAndEcho(string $choiceId, array $consequence, array $momentKeys): void
    {
        $echoMomentKey = $consequence['echo_moment_key'] ?? null;
        if (
            $echoMomentKey !== null
            && $echoMomentKey !== ''
            && ! in_array($echoMomentKey, $momentKeys, true)
        ) {
            throw new RuntimeException("Phase 6 consequence {$choiceId} references an invalid echo_moment_key.");
        }

        if (mb_trim((string) ($consequence['moment_narrator_hook'] ?? '')) === '') {
            throw new RuntimeException("Phase 6 consequence {$choiceId} requires a moment_narrator_hook.");
        }
    }
}
