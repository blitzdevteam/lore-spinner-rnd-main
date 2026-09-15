<?php

declare(strict_types=1);

use App\Ai\Adaptation\RuntimeNarratorPromptValidator;
use App\Ai\Agents\Adaptation\BoilerDraftAgent;
use App\Ai\Agents\Adaptation\BoilerFinalizeAgent;
use App\Ai\Agents\Adaptation\EditorialVerificationAgent;
use App\Ai\Agents\Adaptation\StorySessionMapAgent;
use App\Enums\BoilerStatus;
use App\Services\Runtime\NarrationResultNormalizer;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;

it('uses the six-state Boiler lifecycle', function () {
    expect(array_map(fn (BoilerStatus $status): string => $status->value, BoilerStatus::cases()))
        ->toBe(['pending', 'drafting', 'drafted', 'finalizing', 'finalized', 'failed']);
});

it('pins both Boiler agents to the IP Trimming Opus tier', function () {
    foreach ([BoilerDraftAgent::class, BoilerFinalizeAgent::class] as $agent) {
        $reflection = new ReflectionClass($agent);

        expect($reflection->getAttributes(Provider::class)[0]->getArguments()[0])->toBe('anthropic')
            ->and($reflection->getAttributes(Model::class)[0]->getArguments()[0])->toBe('claude-opus-5')
            ->and($reflection->getAttributes(Timeout::class)[0]->getArguments()[0])->toBe(600);
    }
});

it('keeps Boiler additions out of disabled adaptation schemas', function () {
    $schema = new JsonSchemaTypeFactory();

    expect((new StorySessionMapAgent(false))->schema($schema))->not->toHaveKey('boiling_profile')
        ->and((new StorySessionMapAgent(true))->schema($schema))->toHaveKey('boiling_profile')
        ->and((new EditorialVerificationAgent(false))->schema($schema))->not->toHaveKey('boiler_audit')
        ->and((new EditorialVerificationAgent(true))->schema($schema))->toHaveKey('boiler_audit');
});

it('normalizes legacy choices and Boiler suggestion objects without changing canonical identity', function () {
    $normalizer = new NarrationResultNormalizer();

    expect($normalizer->choices(['choices' => ['Inspect', 'Wait', 'Leave']], false))
        ->toBe(['Inspect', 'Wait', 'Leave'])
        ->and($normalizer->choices([
            'visible_suggestions' => [
                [
                    'text' => 'Hide the shard.',
                    'canonical_choice_id' => 'S1_C1',
                    'canonical_path_label' => 'b',
                ],
                ['text' => 'Inspect the residue.'],
            ],
        ], true))->toBe([
            [
                'text' => 'Hide the shard.',
                'canonical_choice_id' => 'S1_C1',
                'canonical_path_label' => 'B',
            ],
            [
                'text' => 'Inspect the residue.',
                'canonical_choice_id' => null,
                'canonical_path_label' => null,
            ],
        ]);
});

it('rejects missing legacy gameplay choices', function () {
    expect(fn () => (new NarrationResultNormalizer())->choices(['choices' => []], false))
        ->toThrow(RuntimeException::class, 'exactly three choice strings');
});

it('allows no next choices after a legacy session completes', function () {
    expect((new NarrationResultNormalizer())->choices([
        'choices' => [],
        'session_complete' => true,
    ], false))->toBe([]);
});

it('truncates more than three visible suggestions instead of failing the turn', function () {
    $choices = (new NarrationResultNormalizer())->choices([
        'visible_suggestions' => [
            ['text' => 'One'],
            ['text' => 'Two'],
            ['text' => 'Three'],
            ['text' => 'Four'],
        ],
    ], true);

    expect($choices)->toHaveCount(3)
        ->and($choices[0]['text'])->toBe('One')
        ->and($choices[2]['text'])->toBe('Three');
});

it('demotes half-mapped or invalid path suggestions to freeform text', function () {
    $choices = (new NarrationResultNormalizer())->choices([
        'visible_suggestions' => [
            [
                'text' => 'Half mapped',
                'canonical_choice_id' => 'S1_C1',
            ],
            [
                'text' => 'Bad path',
                'canonical_choice_id' => 'S1_C1',
                'canonical_path_label' => 'Z',
            ],
            [
                'text' => 'Keep me',
                'canonical_choice_id' => 'S1_C1',
                'canonical_path_label' => 'A',
            ],
        ],
    ], true);

    expect($choices)->toBe([
        [
            'text' => 'Half mapped',
            'canonical_choice_id' => null,
            'canonical_path_label' => null,
        ],
        [
            'text' => 'Bad path',
            'canonical_choice_id' => null,
            'canonical_path_label' => null,
        ],
        [
            'text' => 'Keep me',
            'canonical_choice_id' => 'S1_C1',
            'canonical_path_label' => 'A',
        ],
    ]);
});

it('validates Boiler and legacy prompt structures with separate contracts', function () {
    $runtimeTokens = implode("\n", [
        '[OPENING_SCENE_INJECTION_POINT]',
        '[SYMBOLIC_MEMORY_INJECTION_POINT]',
        '[ALIGNMENT_TILT_INJECTION_POINT]',
        '[WORLD_STATE_TIERED_INJECTION_POINT]',
        '[CONSEQUENCE_ECHO_INJECTION_POINT]',
        '[SESSION_CLOSE_INJECTION_POINT]',
    ]);
    $validator = new RuntimeNarratorPromptValidator();

    $validator->validate("legacy\n{$runtimeTokens}", false);
    $validator->validate(implode("\n", [
        '[BOILER_RUNTIME_V1]',
        '[ACTIVE_PLAYABLE_MOMENT_INJECTION_POINT]',
        $runtimeTokens,
        'SECTION 12: BOILER PLAYABLE SESSION MAP',
        "ORDERED MOMENTS:\n- 1. S1_M01",
        'BOILER MOMENT_PROGRESS CONTRACT',
        'BOILER VISIBLE_SUGGESTIONS CONTRACT',
        'BOILER PLAYER-CRITIQUE FOOTNOTE',
    ]), true);

    expect(true)->toBeTrue();
});

it('rejects the legacy full-event section in a Boiler prompt', function () {
    $prompt = implode("\n", [
        '[BOILER_RUNTIME_V1]',
        '[ACTIVE_PLAYABLE_MOMENT_INJECTION_POINT]',
        '[OPENING_SCENE_INJECTION_POINT]',
        '[SYMBOLIC_MEMORY_INJECTION_POINT]',
        '[ALIGNMENT_TILT_INJECTION_POINT]',
        '[WORLD_STATE_TIERED_INJECTION_POINT]',
        '[CONSEQUENCE_ECHO_INJECTION_POINT]',
        '[SESSION_CLOSE_INJECTION_POINT]',
        'SECTION 12: FULL CURRENT EPISODE SCRIPT',
        "ORDERED MOMENTS:\n- 1. S1_M01",
        'BOILER MOMENT_PROGRESS CONTRACT',
        'BOILER VISIBLE_SUGGESTIONS CONTRACT',
        'BOILER PLAYER-CRITIQUE FOOTNOTE',
    ]);

    (new RuntimeNarratorPromptValidator())->validate($prompt, true);
})->throws(RuntimeException::class);
