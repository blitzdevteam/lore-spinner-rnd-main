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
     * Parse must_reintroduce into structured items.
     *
     * Handles two formats:
     *   - Phase B (structured array): array of objects with fact, marker,
     *     target_moment_key, and key_terms
     *   - Phase A (flat string): lines with "fact — COLD OPEN" / "fact — LATER"
     *     markers, parsed at runtime
     *
     * @param  array<int, mixed>|string  $mustReintroduce
     * @return array<int, array{fact: string, marker: string, key: string, target_moment_key: string, key_terms: array<int, string>}>
     */
    public function parse(array|string $mustReintroduce): array
    {
        if (is_array($mustReintroduce)) {
            return $this->parseStructured($mustReintroduce);
        }

        return $this->parseFlatString($mustReintroduce);
    }

    /**
     * Parse a Phase B structured array into items.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, array{fact: string, marker: string, key: string, target_moment_key: string, key_terms: array<int, string>}>
     */
    private function parseStructured(array $items): array
    {
        $parsed = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $fact   = trim((string) ($item['fact'] ?? ''));
            $marker = strtoupper(trim((string) ($item['marker'] ?? 'LATER')));
            $target = trim((string) ($item['target_moment_key'] ?? ''));
            $terms  = array_values(array_filter(
                array_map(static fn ($t) => is_string($t) ? trim($t) : '', (array) ($item['key_terms'] ?? [])),
                static fn (string $t): bool => $t !== '',
            ));

            if ($fact === '') {
                continue;
            }

            $parsed[] = [
                'fact'              => $fact,
                'marker'            => $marker === 'COLD_OPEN' ? 'COLD OPEN' : 'LATER',
                'key'               => $this->factKey($fact),
                'target_moment_key' => $target,
                'key_terms'         => $terms,
            ];
        }

        return $parsed;
    }

    /**
     * Parse a Phase A flat must_reintroduce string into structured items.
     *
     * Each line becomes one item. Lines are split on "—" (em dash) or
     * "--" (double hyphen) to separate the fact from its timing marker
     * (COLD OPEN / LATER). Lines without a marker are treated as LATER.
     *
     * @return array<int, array{fact: string, marker: string, key: string, target_moment_key: string, key_terms: array<int, string>}>
     */
    private function parseFlatString(string $mustReintroduce): array
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
                'fact'              => $fact,
                'marker'            => $marker,
                'key'               => $this->factKey($fact),
                'target_moment_key' => '',
                'key_terms'         => [],
            ];
        }

        return $items;
    }

    /**
     * Filter items to only those not yet reintroduced.
     *
     * Optionally filter by active moment key — when target_moment_key is
     * set on an item, it only appears when the active moment matches
     * (or is empty, meaning "any moment").
     *
     * @param  array<int, array{fact: string, marker: string, key: string, target_moment_key: string, key_terms: array<int, string>}>  $items
     * @param  array<int, string>  $reintroducedKeys  Keys already reintroduced
     * @param  string  $activeMomentKey  Current moment key (for target_moment_key filtering)
     * @return array<int, array{fact: string, marker: string, key: string, target_moment_key: string, key_terms: array<int, string>}>
     */
    public function pending(array $items, array $reintroducedKeys, string $activeMomentKey = ''): array
    {
        $done = array_flip($reintroducedKeys);

        return array_values(array_filter(
            $items,
            function (array $item) use ($done, $activeMomentKey): bool {
                if (isset($done[$item['key']])) {
                    return false;
                }

                // If the item has a target_moment_key and we know the
                // active moment, only show it when we're at or past the
                // target. If activeMomentKey is empty (opening or unknown),
                // show all pending.
                $target = $item['target_moment_key'] ?? '';
                if ($target !== '' && $activeMomentKey !== '' && $target !== $activeMomentKey) {
                    return false;
                }

                return true;
            },
        ));
    }

    /**
     * Render pending items as a prompt-ready string.
     * Falls back to the raw string if no items parsed.
     *
     * @param  array<int, array{fact: string, marker: string, key: string, target_moment_key: string, key_terms: array<int, string>}>  $pendingItems
     */
    public function render(array $pendingItems): string
    {
        if ($pendingItems === []) {
            return '';
        }

        $lines = [];
        foreach ($pendingItems as $item) {
            $line = "{$item['fact']} — {$item['marker']}";
            $target = $item['target_moment_key'] ?? '';
            if ($target !== '') {
                $line .= " (target: {$target})";
            }
            $lines[] = $line;
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
