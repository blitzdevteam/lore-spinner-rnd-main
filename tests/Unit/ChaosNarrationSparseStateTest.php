<?php

declare(strict_types=1);

use App\Ai\Agents\Chaos\ChaosNarrationSchema;
use App\Ai\Agents\Chaos\NativeObjectSchema;
use App\Models\Game;
use App\Services\Adaptation\SessionEventLoader;
use App\Services\ChaosEngineService;
use Illuminate\Http\Client\Request;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Http;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Providers\Anthropic\Handlers\Structured as AnthropicStructuredHandler;
use Prism\Prism\Structured\Request as StructuredRequest;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Text\Request as TextRequest;
use Prism\Prism\ValueObjects\Messages\SystemMessage;

function sparseStateEngine(): ChaosEngineService
{
    return new ChaosEngineService(new SessionEventLoader());
}

it('preserves every prior state field when a sparse delta reports null or omits fields', function () {
    $previous = [
        'location' => 'Stoke Moran',
        'conditions' => ['Alert'],
        'items' => ['Lantern'],
        'object_states' => ['safe: locked'],
        'relationship_updates' => ['Holmes: trusting'],
        'world_flags' => ['window: open'],
        'knowledge' => ['A whistle sounds at night'],
        'notes' => ['Helen expects help'],
        'player_style' => ['Protective under pressure'],
        'unresolved_promises' => ['Keep Helen safe'],
        'emotional_ledger' => [
            ['category' => 'promises_made', 'entry' => 'Promised to protect Helen.'],
        ],
    ];

    $engine = sparseStateEngine();
    $merged = $engine->mergeStateDelta($previous, [
        'location' => null,
        'conditions' => null,
        'items' => null,
        'object_states' => null,
        'relationship_updates' => null,
        'world_flags' => null,
        'knowledge' => null,
        'notes' => null,
        'player_style' => null,
        'unresolved_promises' => null,
        'emotional_ledger_entries' => null,
    ]);

    expect($merged)->toBe($previous)
        ->and($engine->mergeStateDelta($previous, []))->toBe($previous);
});

it('clears complete lists only with explicit empty arrays while merging true deltas', function () {
    $previous = [
        'location' => 'Stoke Moran',
        'conditions' => ['Alert'],
        'items' => ['Lantern'],
        'object_states' => ['safe: locked'],
        'relationship_updates' => ['Holmes: trusting'],
        'world_flags' => ['window: closed'],
        'knowledge' => ['A whistle sounds at night'],
        'notes' => [],
        'player_style' => [],
        'unresolved_promises' => ['Keep Helen safe'],
        'emotional_ledger' => [],
    ];

    $merged = sparseStateEngine()->mergeStateDelta($previous, [
        'location' => 'Julia’s room',
        'conditions' => [],
        'items' => null,
        'object_states' => ['safe: opened'],
        'relationship_updates' => null,
        'world_flags' => ['window: open'],
        'knowledge' => ['The bell-rope is a dummy'],
        'notes' => null,
        'player_style' => null,
        'unresolved_promises' => [],
        'emotional_ledger_entries' => null,
    ]);

    expect($merged['location'])->toBe('Julia’s room')
        ->and($merged['conditions'])->toBe([])
        ->and($merged['items'])->toBe(['Lantern'])
        ->and($merged['object_states'])->toBe(['safe: opened'])
        ->and($merged['relationship_updates'])->toBe(['Holmes: trusting'])
        ->and($merged['world_flags'])->toBe(['window: open'])
        ->and($merged['knowledge'])->toBe([
            'A whistle sounds at night',
            'The bell-rope is a dummy',
        ])
        ->and($merged['unresolved_promises'])->toBe([]);
});

it('treats malformed sparse values as no state change instead of breaking play', function () {
    $engine = sparseStateEngine();
    $previous = $engine->emptyWorldState();
    $previous['conditions'] = ['Alert'];
    $previous['items'] = ['Lantern'];

    $merged = $engine->mergeStateDelta($previous, [
        'conditions' => ['Alert', ['not-a-string']],
        'items' => false,
        'emotional_ledger_entries' => [
            'invalid',
            ['category' => '', 'entry' => 'missing category'],
        ],
    ]);

    expect($merged)->toBe($previous);
});

it('uses native-compatible required nullable fields for sparse narration output', function () {
    $schema = (new NativeObjectSchema(
        ChaosNarrationSchema::definition(new JsonSchemaTypeFactory),
    ))->toArray();

    $rootProperties = array_keys($schema['properties']);
    $state = $schema['properties']['state_delta'];
    $countUnionTypes = function (mixed $node) use (&$countUnionTypes): int {
        if (! is_array($node)) {
            return 0;
        }

        $count = isset($node['type']) && is_array($node['type']) ? 1 : 0;

        foreach ($node as $value) {
            $count += $countUnionTypes($value);
        }

        return $count;
    };
    $assertStrictObjects = function (mixed $node) use (&$assertStrictObjects): void {
        if (! is_array($node)) {
            return;
        }

        $types = (array) ($node['type'] ?? []);
        if (in_array('object', $types, true) && isset($node['properties'])) {
            expect($node['additionalProperties'] ?? null)->toBeFalse()
                ->and($node['required'] ?? [])->toBe(array_keys($node['properties']));
        }

        foreach ($node as $value) {
            $assertStrictObjects($value);
        }
    };
    $assertStrictObjects($schema);

    expect($schema['additionalProperties'])->toBeFalse()
        ->and($schema['required'])->toBe($rootProperties)
        ->and(json_encode($schema))->not->toContain('"title"')
        ->and($state['additionalProperties'])->toBeFalse()
        ->and($state['required'])->toBe(array_keys($state['properties']))
        ->and($state['properties']['conditions']['type'])->toBe(['array', 'null'])
        ->and($state['properties']['location']['type'])->toBe(['string', 'null'])
        ->and($schema['properties']['visible_suggestions']['type'])->toBe('array')
        ->and($schema['properties']['moment_progress']['type'])->toBe('object')
        ->and($countUnionTypes($schema))->toBe(11);
});

it('builds Anthropic narration requests with GA native output config instead of a schema tool', function () {
    $schema = new NativeObjectSchema(
        ChaosNarrationSchema::definition(new JsonSchemaTypeFactory),
    );
    $cachedSystemPrompt = new SystemMessage('Static narrator contract.');
    $cachedSystemPrompt->withProviderOptions([
        'cacheType' => 'ephemeral',
        'cacheTtl' => '1h',
    ]);
    $request = Prism::structured()
        ->using('anthropic', 'claude-sonnet-4-6')
        ->withSchema($schema)
        ->withSystemPrompts([
            $cachedSystemPrompt,
            new SystemMessage('Dynamic runtime state.'),
        ])
        ->withPrompt('Continue the story.')
        ->toRequest();

    $payload = AnthropicStructuredHandler::buildHttpRequestPayload($request);

    expect($payload)->toHaveKey('output_config.format')
        ->and($payload['output_config']['format']['type'])->toBe('json_schema')
        ->and($payload['output_config']['format']['schema'])->toBe($schema->toArray())
        ->and($payload)->not->toHaveKey('tools')
        ->and($payload)->not->toHaveKey('tool_choice')
        ->and($payload['system'][0]['cache_control'])->toBe([
            'type' => 'ephemeral',
            'ttl' => '1h',
        ]);
});

it('parses a native Anthropic narration response and records its field-size composition', function () {
    $structured = [
        'response' => '<p>The room answers.</p>',
        'choices' => [],
        'visible_suggestions' => [],
        'moment_progress' => [
            'moment_key' => '',
            'exit_condition_met' => false,
            'moment_footnote' => '',
        ],
        'session_complete' => false,
        'state_delta' => [
            'location' => null,
            'conditions' => null,
            'items' => null,
            'object_states' => null,
            'relationship_updates' => null,
            'world_flags' => null,
            'knowledge' => null,
            'notes' => null,
            'player_style' => null,
            'unresolved_promises' => null,
            'emotional_ledger_entries' => null,
        ],
        'alignment_tally_delta' => ['chaotic' => 0, 'lawful' => 0, 'neutral' => 0],
        'is_climactic_choice' => false,
        'defining_choice_id' => '',
        'defining_path_label' => '',
        'defining_choice_line' => '',
        'symbolic_memory_update' => '',
        'session_memory_update' => '',
    ];

    Http::fake([
        '*' => Http::response([
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-sonnet-4-6',
            'content' => [
                ['type' => 'text', 'text' => json_encode($structured, JSON_THROW_ON_ERROR)],
            ],
            'stop_reason' => 'end_turn',
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 40,
                'cache_creation_input_tokens' => 80,
                'cache_read_input_tokens' => 0,
            ],
        ], 200),
    ]);

    $result = sparseStateEngine()->callAgent(
        model: 'claude-sonnet-4-6',
        systemPrompt: "Static contract.\n### SECTION 8:\nDynamic runtime state.",
        conversationHistory: [],
        playerAction: null,
        protagonist: 'Holmes',
        temperature: 0.6,
    );

    expect($result['response'])->toBe('<p>The room answers.</p>')
        ->and($result['state_delta']['conditions'])->toBeNull()
        ->and($result['_usage']['prompt_tokens'])->toBe(100)
        ->and($result['_output_metrics']['narration_field_bytes'])->toBe(24)
        ->and($result['_output_metrics']['state_delta_bytes'])->toBeGreaterThan(0)
        ->and($result['_output_metrics']['structured_response_bytes'])
        ->toBeGreaterThan($result['_output_metrics']['narration_field_bytes']);

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        return data_get($payload, 'output_config.format.type') === 'json_schema'
            && ! array_key_exists('tools', $payload)
            && data_get($payload, 'system.0.cache_control') === [
                'type' => 'ephemeral',
                'ttl' => '1h',
            ];
    });
});

it('repairs missing choices after streaming without replacing the streamed narration', function () {
    $streamed = [
        'response' => '<p>The door opens onto the rain.</p>',
        'choices' => [],
        'visible_suggestions' => [],
        'moment_progress' => [
            'moment_key' => '',
            'exit_condition_met' => false,
            'moment_footnote' => '',
        ],
        'session_complete' => false,
        'state_delta' => [
            'location' => null,
            'conditions' => null,
            'items' => null,
            'object_states' => null,
            'relationship_updates' => null,
            'world_flags' => null,
            'knowledge' => null,
            'notes' => null,
            'player_style' => null,
            'unresolved_promises' => null,
            'emotional_ledger_entries' => null,
        ],
        'alignment_tally_delta' => ['chaotic' => 0, 'lawful' => 0, 'neutral' => 0],
        'is_climactic_choice' => false,
        'defining_choice_id' => '',
        'defining_path_label' => '',
        'defining_choice_line' => '',
        'symbolic_memory_update' => '',
        'session_memory_update' => '',
    ];

    $fake = Prism::fake([
        TextResponseFake::make()->withText(json_encode($streamed, JSON_THROW_ON_ERROR)),
        StructuredResponseFake::make()->withStructured([
            'choices' => ['Enter the room', 'Wait beneath the awning', 'Circle around the house'],
        ]),
    ]);
    $game = new Game;
    $game->setRawAttributes([
        'id' => 'game-test',
        'current_playable_moment_id' => null,
    ]);
    $deltas = '';

    $result = sparseStateEngine()->callAgentStreaming(
        model: 'claude-haiku-4-5',
        systemPrompt: 'Narrate the next turn.',
        conversationHistory: [],
        playerAction: 'Open the door.',
        protagonist: 'Watson',
        onResponseDelta: function (string $delta) use (&$deltas): void {
            $deltas .= $delta;
        },
        game: $game,
    );

    expect($deltas)->toBe($streamed['response'])
        ->and($result['response'])->toBe($streamed['response'])
        ->and($result['choices'])->toBe([
            'Enter the room',
            'Wait beneath the awning',
            'Circle around the house',
        ]);

    $fake->assertCallCount(2);
    $fake->assertRequest(function (array $requests): void {
        expect($requests[0])->toBeInstanceOf(TextRequest::class)
            ->and($requests[1])->toBeInstanceOf(StructuredRequest::class)
            ->and($requests[1]->schema()->toArray()['properties']['choices']['minItems'])->toBe(3)
            ->and($requests[1]->schema()->toArray()['properties']['choices']['maxItems'])->toBe(3);
    });
});
