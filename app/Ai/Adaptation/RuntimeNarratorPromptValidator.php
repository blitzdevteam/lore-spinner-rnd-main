<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use Illuminate\Support\Facades\Log;
use RuntimeException;

final class RuntimeNarratorPromptValidator
{
    private const array RUNTIME_TOKENS = [
        '[OPENING_SCENE_INJECTION_POINT]',
        '[SYMBOLIC_MEMORY_INJECTION_POINT]',
        '[ALIGNMENT_TILT_INJECTION_POINT]',
        '[WORLD_STATE_TIERED_INJECTION_POINT]',
        '[CONSEQUENCE_ECHO_INJECTION_POINT]',
        '[SESSION_CLOSE_INJECTION_POINT]',
    ];

    private const array ALLOWED_CONTROL_TOKENS = [
        '[THINKING]',
        '[SESSION_COMPLETE]',
        '[POPULATED]',
        '[RUNTIME]',
        '[HARDCODED]',
        // Beat-type labels rendered inline in the beat map section (line: [SETUP] 00:00-03:00: ...)
        '[SETUP]',
        '[ESCALATION]',
        '[BREATH]',
        '[TWIST]',
        '[RESOLUTION]',
        '[BRANCHING]',
    ];

    /**
     * Agency guarantees that must survive every future edit to the template.
     * Losing any of them reintroduces the failure where the narrator quietly
     * downgrades a canon-safe player action into a tamer one.
     *
     * @var list<string>
     */
    private const array REQUIRED_AGENCY_BLOCKS = [
        'PLAYER ACTION EXECUTION — LITERAL FIRST',
        'ORDER OF OPERATIONS: PLAYER INTENT → LITERAL EXECUTION → WORLD/NPC REACTION → LOCAL CONSEQUENCE → NARRATIVE GRAVITY.',
        'NO SILENT SUBSTITUTION',
        'ACTION FIDELITY',
    ];

    /**
     * Prose-discipline guarantees. Separate from the agency contract above:
     * these govern how the narrator writes a turn, not what it lets happen.
     *
     * @var list<string>
     */
    private const array REQUIRED_PROSE_BLOCKS = [
        'INTERPRETIVE GLOSS',
        'NO REHASHING',
    ];

    public function validate(string $prompt, bool $boilerEnabled): void
    {
        $length = mb_strlen($prompt);
        if ($length === 0) {
            throw new RuntimeException('Runtime narrator prompt is empty.');
        }

        // The builder already exhausted its compression cascade before returning
        // an over-cap prompt, so rejecting it here would discard a finalized cut
        // that has no smaller representation. Narration runs on a 200k-context
        // model; warn and keep the prompt rather than failing the session.
        if ($length > RuntimeNarratorTemplateBuilder::MAX_PROMPT_CHARS) {
            Log::channel('narration')->warning('runtime_narrator_prompt.over_cap_accepted', [
                'char_count' => $length,
                'cap' => RuntimeNarratorTemplateBuilder::MAX_PROMPT_CHARS,
                'overage' => $length - RuntimeNarratorTemplateBuilder::MAX_PROMPT_CHARS,
            ]);
        }

        if (preg_match('/\{\{[^}]+\}\}/', $prompt)) {
            throw new RuntimeException('Runtime narrator prompt contains an unresolved adaptation-time template token.');
        }

        foreach ([...self::REQUIRED_AGENCY_BLOCKS, ...self::REQUIRED_PROSE_BLOCKS] as $block) {
            if (! str_contains($prompt, $block)) {
                throw new RuntimeException("Runtime narrator prompt is missing the required narrator contract block \"{$block}\".");
            }
        }

        foreach (self::RUNTIME_TOKENS as $token) {
            if (mb_substr_count($prompt, $token) !== 1) {
                throw new RuntimeException("Runtime narrator prompt must contain {$token} exactly once.");
            }
        }

        if (! $boilerEnabled) {
            if (str_contains($prompt, '[BOILER_RUNTIME_V1]') || str_contains($prompt, '[ACTIVE_PLAYABLE_MOMENT_INJECTION_POINT]')) {
                throw new RuntimeException('Legacy runtime prompt unexpectedly contains Boiler markers.');
            }

            return;
        }

        foreach (['[BOILER_RUNTIME_V1]', '[ACTIVE_PLAYABLE_MOMENT_INJECTION_POINT]'] as $marker) {
            if (mb_substr_count($prompt, $marker) !== 1) {
                throw new RuntimeException("Boiler runtime prompt must contain {$marker} exactly once.");
            }
        }

        if (str_contains($prompt, 'SECTION 12: FULL CURRENT EPISODE SCRIPT')) {
            throw new RuntimeException('Boiler runtime prompt rendered the legacy full-event section.');
        }

        foreach ([
            'SECTION 12: BOILER PLAYABLE SESSION MAP',
            'BOILER MOMENT_PROGRESS CONTRACT',
            'BOILER VISIBLE_SUGGESTIONS CONTRACT',
            'BOILER PLAYER-DIRECTION FOOTNOTE',
        ] as $requiredBlock) {
            if (! str_contains($prompt, $requiredBlock)) {
                throw new RuntimeException("Boiler runtime prompt is missing {$requiredBlock}.");
            }
        }

        if (! preg_match('/ORDERED MOMENTS:\s*\R-\s+1\./u', $prompt)) {
            throw new RuntimeException('Boiler runtime prompt contains an empty ordered moment map.');
        }

        preg_match_all('/\[[A-Z][A-Z0-9_]*\]/', $prompt, $matches);
        $allowed = [
            ...self::RUNTIME_TOKENS,
            ...self::ALLOWED_CONTROL_TOKENS,
            '[BOILER_RUNTIME_V1]',
            '[ACTIVE_PLAYABLE_MOMENT_INJECTION_POINT]',
        ];

        foreach (array_unique($matches[0] ?? []) as $token) {
            if (! in_array($token, $allowed, true)) {
                throw new RuntimeException("Boiler runtime prompt contains unknown control token {$token}.");
            }
        }
    }
}
