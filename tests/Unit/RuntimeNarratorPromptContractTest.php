<?php

declare(strict_types=1);

use App\Ai\Adaptation\RuntimeNarratorPromptValidator;

function narratorTemplateSource(): string
{
    return (string) file_get_contents(
        resource_path('views/ai/agents/chaos/runtime-narrator-template.blade.php')
    );
}

/**
 * The validator matches these blocks literally, so the tests read them from the
 * validator rather than restating them — a typo'd em dash or arrow in either
 * place has to fail here instead of at session assembly.
 *
 * @return list<string>
 */
function requiredNarratorBlocks(): array
{
    $validator = new ReflectionClass(RuntimeNarratorPromptValidator::class);

    return [
        ...$validator->getConstant('REQUIRED_AGENCY_BLOCKS'),
        ...$validator->getConstant('REQUIRED_PROSE_BLOCKS'),
    ];
}

function promptWithRequiredBlocks(string $omit = ''): string
{
    $prompt = implode("\n", array_filter(
        requiredNarratorBlocks(),
        fn (string $block): bool => $block !== $omit,
    ));

    foreach ([
        '[OPENING_SCENE_INJECTION_POINT]',
        '[SYMBOLIC_MEMORY_INJECTION_POINT]',
        '[ALIGNMENT_TILT_INJECTION_POINT]',
        '[WORLD_STATE_TIERED_INJECTION_POINT]',
        '[CONSEQUENCE_ECHO_INJECTION_POINT]',
        '[SESSION_CLOSE_INJECTION_POINT]',
    ] as $token) {
        $prompt .= "\n".$token;
    }

    return $prompt;
}

it('carries every block the validator demands, byte for byte', function (): void {
    $template = narratorTemplateSource();

    expect(requiredNarratorBlocks())->not->toBeEmpty();

    foreach (requiredNarratorBlocks() as $block) {
        expect($template)->toContain($block);
    }
});

it('accepts a prompt carrying every required contract block', function (): void {
    (new RuntimeNarratorPromptValidator)->validate(promptWithRequiredBlocks(), false);
})->throwsNoExceptions();

it('rejects a prompt that dropped a required contract block', function (string $block): void {
    (new RuntimeNarratorPromptValidator)->validate(promptWithRequiredBlocks($block), false);
})
    ->with(fn (): array => requiredNarratorBlocks())
    ->throws(RuntimeException::class, 'missing the required narrator contract block');

/**
 * Section 15/18 literal-execution contract, added after the narrator downgraded
 * a slap into a shoulder-touch and turned "get up and dance" into standing
 * beside the set.
 */
describe('agency contract', function (): void {
    it('runs literal execution before the freeform classification', function (): void {
        $template = narratorTemplateSource();

        $literalFirst = mb_strpos($template, 'PLAYER ACTION EXECUTION — LITERAL FIRST');
        $classify = mb_strpos($template, 'FREEFORM RESOLUTION: classify and resolve:');

        expect($literalFirst)->toBeInt()
            ->and($classify)->toBeInt()
            ->and($literalFirst)->toBeLessThan($classify);
    });

    it('names the failure modes that produced the slap and the dance', function (): void {
        expect(narratorTemplateSource())
            ->toContain('DO NOT CONVERT THE ACTION INTO PREPARATION FOR THE ACTION.')
            ->toContain('SOCIAL RULES ARE CONSEQUENCES, NOT PROHIBITIONS.')
            ->toContain('DO NOT INVENT THE COOPERATION THE ACTION NEEDS.');
    });

    it('keeps the examples free of characters from other stories', function (): void {
        // The template is hardcoded and renders for every adaptation, so a named
        // outside character would sit in the context of unrelated stories.
        $block = narratorContractBlock('PLAYER ACTION EXECUTION — LITERAL FIRST', 'FREEFORM RESOLUTION:');

        expect($block)->not->toContain('Holmes')
            ->and($block)->not->toContain('Regency')
            ->and($block)->not->toContain('Elizabeth');
    });
});

/**
 * The interpretive-gloss family ("with the expression of someone who...") is
 * interiority wearing an observation's clothes, so it slipped past the existing
 * cognitive-narration ban.
 */
describe('prose discipline', function (): void {
    it('bans the gloss inside the universal hard bans, not a later section', function (): void {
        $template = narratorTemplateSource();

        $universalBans = mb_strpos($template, 'UNIVERSAL BANS:');
        $gloss = mb_strpos($template, 'INTERPRETIVE GLOSS');
        $ipBans = mb_strpos($template, 'IP-SPECIFIC BANS:');

        expect($gloss)->toBeGreaterThan($universalBans)
            ->and($gloss)->toBeLessThan($ipBans);
    });

    it('supplies the deletion test rather than only a blacklist', function (): void {
        // A bare string blacklist gets evaded by the next variant, so the ban
        // has to name the mechanism and hand over a checkable operation.
        $block = narratorContractBlock('INTERPRETIVE GLOSS', '- NAMES:');

        expect($block)
            ->toContain('THE TEST')
            ->toContain('delete the comparison and read what remains')
            ->toContain('of a man/woman/person who')
            ->toContain('face suggesting');
    });

    it('pairs a banned line with its rewrite', function (): void {
        $block = narratorContractBlock('INTERPRETIVE GLOSS', '- NAMES:');

        expect($block)
            ->toContain('is the ban.')
            ->toContain('is the fix');
    });

    it('is searchable from the self-check', function (): void {
        $selfCheck = narratorContractBlock('SELF-CHECK', 'ENDING OVERRIDE');

        expect($selfCheck)
            ->toContain('INTERPRETIVE GLOSS')
            ->toContain('string-search');
    });
});

function narratorContractBlock(string $from, string $until): string
{
    $template = narratorTemplateSource();

    $start = mb_strpos($template, $from);
    expect($start)->toBeInt();

    $end = mb_strpos($template, $until, $start);
    expect($end)->toBeInt();

    return mb_substr($template, $start, $end - $start);
}
