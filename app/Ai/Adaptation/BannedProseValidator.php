<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

/**
 * Deterministic voice-ban scanner for adaptation artifacts.
 *
 * Runs inside the generate-validate-repair loop (GeneratesValidatedArtifact)
 * so voice violations are caught on the same pass as structural ones, not
 * deferred to Editorial Verification at the end of the pipeline.
 *
 * This is a GATE, not an EDITOR — it refuses to ship bad output, it never
 * rewrites prose. The repair prompt tells the model exactly what to fix and
 * the generator re-runs.
 *
 * Checks (exact match, no fuzzy style judgment):
 *   - Em dash character (—) and double-hyphen (--)
 *   - Semicolons in prose fields
 *   - Ellipses (...) in prose fields
 *   - Trailing "like a/an [noun]" similes in action lines
 *   - Banned vocabulary from the voice profile's universal + IP-specific bans
 *
 * Tolerance: a single soft-ban occurrence (trailing simile) logs a warning
 * but does not reject. Three or more soft-ban occurrences reject. Any
 * hard-ban occurrence rejects immediately.
 */
final class BannedProseValidator
{
    /**
     * Patterns that always reject — no tolerance.
     * Em dash in all variants, double-hyphen, semicolons, ellipses.
     */
    private const HARD_BAN_PATTERNS = [
        'em_dash'      => '/\x{2014}|\x{2013}|\x{2015}/u',
        'double_hyphen' => '/--/',
        'semicolon'    => '/;/',
        'ellipsis'     => '/\.\.\./u',
    ];

    /**
     * Soft-ban pattern: trailing simile in action lines.
     * Tolerance: warn on 1-2, reject on 3+.
     */
    private const SIMILE_PATTERN = '/\blike\s+(?:a|an)\s+\w+/i';

    /**
     * Universal banned vocabulary — applies to every IP.
     * Supplemented by the voice profile's ip_specific_bans at runtime.
     */
    private const UNIVERSAL_BANNED_WORDS = [
        'tapestry', 'delve', 'underscore', 'highlight', 'showcase',
        'intricate', 'swift', 'meticulous', 'adept',
        'nestled', 'tucked away',
        'etch', 'etched',
    ];

    /**
     * Universal banned phrase-tells.
     */
    private const UNIVERSAL_BANNED_PHRASES = [
        'something shifted',
        'something clicked',
        'something broke',
        'breath they didn\'t know',
        'breath they didn’t know',
        'would later remember',
        'little did she know',
        'little did he know',
        'a reminder that',
        'a testament to',
        'it was clear that',
        'the kind of moment',
        'the kind of',
        'she couldn\'t quite',
        'she couldn’t quite',
        'he couldn\'t quite',
        'he couldn’t quite',
        'there was a quality to',
        'it occurred to her that',
        'it occurred to him that',
    ];

    /**
     * Scan prose fields in an artifact for banned patterns.
     *
     * @param  array<string, mixed>  $artifact   The generated artifact (draft or final cut)
     * @param  array<string, mixed>  $voiceProfile  Full voice profile with master_rule_1_hard_bans
     * @param  array<int, string>    $prosePaths  Dot-notation paths to prose fields to scan
     * @return array{errors: array<int, string>, warnings: array<int, string>}
     */
    public function scan(array $artifact, array $voiceProfile, array $prosePaths): array
    {
        $errors   = [];
        $warnings = [];

        $ipBannedWords = $this->extractIpBannedWords($voiceProfile);
        $bannedWords    = array_unique(array_merge(self::UNIVERSAL_BANNED_WORDS, $ipBannedWords));

        $proseValues = $this->extractProseValues($artifact, $prosePaths);

        foreach ($proseValues as $path => $text) {
            foreach (self::HARD_BAN_PATTERNS as $name => $pattern) {
                if (preg_match($pattern, $text)) {
                    $errors[] = "{$path}: banned {$name} found. Replace with a period, comma, or restructure the sentence.";
                }
            }

            foreach ($bannedWords as $word) {
                if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $text)) {
                    $errors[] = "{$path}: banned vocabulary '{$word}'. Use the author's diction from the voice profile instead.";
                }
            }

            foreach (self::UNIVERSAL_BANNED_PHRASES as $phrase) {
                if (mb_stripos($text, $phrase) !== false) {
                    $errors[] = "{$path}: banned phrase-tell '{$phrase}'. Rewrite in the author's voice.";
                }
            }

            $simileCount = preg_match_all(self::SIMILE_PATTERN, $text) ?: 0;
            if ($simileCount >= 3) {
                $errors[] = "{$path}: {$simileCount} trailing similes (like a/an ...). Replace with blunt concrete description.";
            } elseif ($simileCount >= 1) {
                $warnings[] = "{$path}: {$simileCount} trailing simile(s) — consider replacing with concrete description.";
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Extract banned words from the voice profile's IP-specific bans.
     *
     * @param  array<string, mixed>  $voiceProfile
     * @return array<int, string>
     */
    private function extractIpBannedWords(array $voiceProfile): array
    {
        $words = [];
        $ipBans = (array) data_get($voiceProfile, 'master_rule_1_hard_bans.ip_specific_bans', []);

        foreach ($ipBans as $ban) {
            if (! is_array($ban)) {
                continue;
            }

            $banText = (string) ($ban['ban'] ?? '');
            if ($banText === '') {
                continue;
            }

            // Extract the key noun/phrase from the ban text — the word itself,
            // not the full explanation. Heuristic: text before "in", "as",
            // "for", or "when" (e.g., "jaw in action lines" → "jaw").
            if (preg_match('/^([^.!?]{2,40})\s+(?:in|as|for|when)\b/i', $banText, $m)) {
                $candidate = mb_trim($m[1]);
                if (mb_strlen($candidate) >= 2 && mb_strlen($candidate) <= 40) {
                    $words[] = $candidate;
                }
            }
        }

        return $words;
    }

    /**
     * Extract prose values from an artifact by dot-notation paths.
     * Handles both scalar fields and arrays of strings. Expands `.*`
     * wildcards into concrete indices.
     *
     * @param  array<string, mixed>  $artifact
     * @param  array<int, string>    $paths
     * @return array<string, string>
     */
    private function extractProseValues(array $artifact, array $paths): array
    {
        $values = [];

        foreach ($paths as $path) {
            // Expand wildcards: "source_extracts.*" → "source_extracts.0", etc.
            if (str_contains($path, '.*')) {
                [$prefix, $suffix] = str_contains($path, '.*.')
                    ? explode('.*.', $path, 2)
                    : [substr($path, 0, -2), ''];

                $collection = data_get($artifact, $prefix);

                if (! is_array($collection)) {
                    continue;
                }

                foreach (array_keys($collection) as $index) {
                    $subPath = $suffix !== ''
                        ? "{$prefix}.{$index}.{$suffix}"
                        : "{$prefix}.{$index}";
                    $value = data_get($artifact, $subPath);

                    if (is_string($value) && mb_trim($value) !== '') {
                        $values[$subPath] = $value;
                    }
                }

                continue;
            }

            $value = data_get($artifact, $path);

            if (is_string($value) && mb_trim($value) !== '') {
                $values[$path] = $value;
            } elseif (is_array($value)) {
                foreach ($value as $index => $item) {
                    if (is_string($item) && mb_trim($item) !== '') {
                        $values["{$path}.{$index}"] = $item;
                    }
                }
            }
        }

        return $values;
    }
}
