<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

/**
 * Parses the flat `must_reintroduce` string from Entry Point Diagnosis
 * into structured items, tracks which have been reintroduced, and
 * returns only pending items for runtime injection.
 *
 * Phase A (runtime-only, no adaptation regen needed):
 *   - Parses "fact — COLD OPEN" / "fact — LATER" markers
 *   - Tracks done items in game world_state.reintroduced_facts
 *   - Injects only pending items into the runtime prompt
 *   - Narrator reports which items it wove in via reintroduced_facts
 *
 * The tracker is deliberately permissive: if parsing fails, it falls
 * back to injecting the raw string (current behavior). No item is ever
 * silently dropped — if it can't be parsed, it's injected as-is.
 */
final class ReintroTracker
{
    /**
     * Parse a flat must_reintroduce string into structured items.
     *
     * Each line becomes one item. Lines are split on "—" (em dash) or
     * "--" (double hyphen) to separate the fact from its timing marker
     * (COLD OPEN / LATER). Lines without a marker are treated as LATER.
     *
     * @return array<int, array{fact: string, marker: string, key: string}>
     */
    public function parse(string $mustReintroduce): array
    {
        $mustReintroduce = trim($mustReintroduce);
        if ($mustReintroduce === '') {
            return [];
        }

        $lines = preg_split("/\r\n|\n|\r/", $mustReintroduce) ?: [];
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Split on em dash or double-hyphen, but only if followed by
            // a timing marker. This preserves facts that contain dashes
            // for other reasons.
            $marker = 'LATER';
            $fact   = $line;

            if (preg_match('/^(.+?)\s*[\x{2014}\x{2013}\x{2015}]\s*(COLD\s+OPEN|LATER)\s*$/iu', $line, $m)) {
                $fact   = trim($m[1]);
                $marker = strtoupper(preg_replace('/\s+/', ' ', $m[2]));
            } elseif (preg_match('/^(.+?)\s*--\s*(COLD\s+OPEN|LATER)\s*$/iu', $line, $m)) {
                $fact   = trim($m[1]);
                $marker = strtoupper(preg_replace('/\s+/', ' ', $m[2]));
            }

            $items[] = [
                'fact'   => $fact,
                'marker' => $marker,
                'key'    => $this->factKey($fact),
            ];
        }

        return $items;
    }

    /**
     * Filter items to only those not yet reintroduced.
     *
     * @param  array<int, array{fact: string, marker: string, key: string}>  $items
     * @param  array<int, string>  $reintroducedKeys  Keys already reintroduced
     * @return array<int, array{fact: string, marker: string, key: string}>
     */
    public function pending(array $items, array $reintroducedKeys): array
    {
        $done = array_flip($reintroducedKeys);

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => ! isset($done[$item['key']]),
        ));
    }

    /**
     * Render pending items as a prompt-ready string.
     * Falls back to the raw string if no items parsed.
     *
     * @param  array<int, array{fact: string, marker: string, key: string}>  $pendingItems
     */
    public function render(array $pendingItems): string
    {
        if ($pendingItems === []) {
            return '';
        }

        $lines = [];
        foreach ($pendingItems as $item) {
            $lines[] = "{$item['fact']} — {$item['marker']}";
        }

        return implode("\n", $lines);
    }

    /**
     * Extract keys from the narrator's reintroduced_facts report.
     * The narrator reports the facts it wove into this turn's narration;
     * we convert them to keys for tracking.
     *
     * @param  array<int, string>|null  $reportedFacts
     * @return array<int, string>
     */
    public function keysFromReportedFacts(?array $reportedFacts): array
    {
        if ($reportedFacts === null) {
            return [];
        }

        $keys = [];
        foreach ($reportedFacts as $fact) {
            if (! is_string($fact) || trim($fact) === '') {
                continue;
            }
            $keys[] = $this->factKey($fact);
        }

        return $keys;
    }

    /**
     * Generate a stable key for a fact so it can be tracked across turns.
     * Normalizes to lowercase, collapses whitespace, and takes the first
     * 8 distinctive words to match across rephrasings.
     */
    private function factKey(string $fact): string
    {
        $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(trim($fact))) ?? mb_strtolower($fact);
        $words = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Take first 8 words — enough to identify the fact without being
        // so long that a rephrasing escapes matching.
        $keyWords = array_slice($words, 0, 8);

        return implode(' ', $keyWords);
    }
}
