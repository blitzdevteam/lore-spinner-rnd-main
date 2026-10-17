<?php

declare(strict_types=1);

use App\Ai\Agents\Adaptation\BoilerDraftAgent;
use App\Ai\Agents\Adaptation\BoilerFinalizeAgent;
use App\Ai\Agents\Adaptation\IpTrimmingChapterAgent;
use App\Ai\AnthropicThinkingPolicy;
use App\Services\ChaosEngineService;
use GuzzleHttp\Psr7\Request;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;

/**
 * @param  array<string, mixed>  $payload
 */
function anthropicRequest(array $payload, string $url = 'https://api.anthropic.com/v1/messages'): Request
{
    return new Request('POST', $url, ['Content-Type' => 'application/json'], (string) json_encode($payload));
}

/**
 * @return array<string, mixed>
 */
function decodedBody(Psr\Http\Message\RequestInterface $request): array
{
    return (array) json_decode((string) $request->getBody(), true);
}

it('disables thinking on Opus 5 Anthropic requests', function () {
    $request = AnthropicThinkingPolicy::disableOpusThinking(
        anthropicRequest(['model' => 'claude-opus-5', 'max_tokens' => 64000])
    );

    expect(decodedBody($request)['thinking'])->toBe(['type' => 'disabled']);
});

it('never sends an effort level alongside disabled thinking', function () {
    // Opus 5 rejects `disabled` thinking with xhigh/max effort on every request,
    // so the policy must not introduce an effort field of its own.
    $request = AnthropicThinkingPolicy::disableOpusThinking(
        anthropicRequest(['model' => 'claude-opus-5'])
    );

    expect(decodedBody($request))->not->toHaveKey('output_config');
});

it('keeps the rewritten body and Content-Length in sync', function () {
    $request = AnthropicThinkingPolicy::disableOpusThinking(
        anthropicRequest(['model' => 'claude-opus-5', 'max_tokens' => 64000])
    );

    expect($request->getHeaderLine('Content-Length'))
        ->toBe((string) mb_strlen((string) $request->getBody(), '8bit'));
});

it('leaves models that reject the disabled thinking type untouched', function (string $model) {
    $request = AnthropicThinkingPolicy::disableOpusThinking(
        anthropicRequest(['model' => $model])
    );

    expect(decodedBody($request))->not->toHaveKey('thinking');
})->with(['claude-opus-4-8', 'claude-opus-4-7', 'claude-sonnet-4-6', 'claude-haiku-4-5', 'claude-haiku-5-5']);

it('respects an explicitly configured thinking field', function () {
    $request = AnthropicThinkingPolicy::disableOpusThinking(
        anthropicRequest(['model' => 'claude-opus-5', 'thinking' => ['type' => 'adaptive']])
    );

    expect(decodedBody($request)['thinking'])->toBe(['type' => 'adaptive']);
});

it('ignores non-Anthropic and non-message requests', function (string $url) {
    $request = AnthropicThinkingPolicy::disableOpusThinking(
        anthropicRequest(['model' => 'claude-opus-5'], $url)
    );

    expect(decodedBody($request))->not->toHaveKey('thinking');
})->with([
    'https://api.openai.com/v1/messages',
    'https://api.anthropic.com/v1/messages/count_tokens',
]);

it('leaves malformed bodies alone', function () {
    $request = new Request('POST', 'https://api.anthropic.com/v1/messages', [], 'not-json');

    expect((string) AnthropicThinkingPolicy::disableOpusThinking($request)->getBody())->toBe('not-json');
});

it('runs the Anthropic pipeline agents on Opus 5.5', function (string $agent) {
    $reflection = new ReflectionClass($agent);

    expect($reflection->getAttributes(Provider::class)[0]->getArguments()[0])->toBe('anthropic')
        ->and($reflection->getAttributes(Model::class)[0]->getArguments()[0])->toBe('claude-opus-5-5');
})->with([BoilerDraftAgent::class, BoilerFinalizeAgent::class]);

it('runs chapter trimming on GPT-6 Luna with low reasoning and no sampling parameters', function () {
    $reflection = new ReflectionClass(IpTrimmingChapterAgent::class);

    expect($reflection->getAttributes(Provider::class))->toBe([])
        ->and($reflection->getAttributes(Model::class)[0]->getArguments()[0])->toBe('gpt-6-luna')
        ->and($reflection->getAttributes(Temperature::class))->toBe([])
        ->and((new IpTrimmingChapterAgent)->providerOptions('openai'))->toBe(['reasoning' => ['effort' => 'low']]);
});

it('never sends disabled thinking to Opus 5.5', function () {
    $untouched = AnthropicThinkingPolicy::disableOpusThinking(anthropicRequest(['model' => 'claude-opus-5-5']));
    $stripped = AnthropicThinkingPolicy::disableOpusThinking(
        anthropicRequest(['model' => 'claude-opus-5-5', 'thinking' => ['type' => 'disabled']])
    );

    expect(decodedBody($untouched))->not->toHaveKey('thinking')
        ->and(decodedBody($stripped))->not->toHaveKey('thinking');
});

it('makes the Haiku 5.5 narration tool strict without enabling manual thinking', function () {
    $request = AnthropicThinkingPolicy::disableOpusThinking(anthropicRequest([
        'model' => 'claude-haiku-5-5',
        'tool_choice' => ['type' => 'tool', 'name' => 'emit_narration'],
        'tools' => [[
            'name' => 'emit_narration',
            'input_schema' => [
                'type' => 'object',
                'properties' => ['response' => ['type' => 'string']],
                'required' => ['response'],
            ],
        ]],
        'temperature' => 0.7,
    ]));

    $body = decodedBody($request);

    expect($body)->not->toHaveKeys(['thinking', 'temperature'])
        ->and($body['tool_choice'])->toBe(['type' => 'tool', 'name' => 'emit_narration'])
        ->and($body['tools'][0]['strict'])->toBeTrue()
        ->and($body['tools'][0]['input_schema']['additionalProperties'])->toBeFalse();
});

it('turns forced tool use into auto with a system instruction for Opus 5.5', function () {
    $request = AnthropicThinkingPolicy::disableOpusThinking(anthropicRequest([
        'model' => 'claude-opus-5-5',
        'system' => [['type' => 'text', 'text' => 'Base prompt.']],
        'tool_choice' => ['type' => 'tool', 'name' => 'output_structured_data'],
        'temperature' => 0.6,
    ]));

    $body = decodedBody($request);

    expect($body['tool_choice'])->toBe(['type' => 'auto'])
        ->and($body)->not->toHaveKey('temperature')
        ->and($body['system'])->toHaveCount(2)
        ->and($body['system'][1]['text'])->toContain('output_structured_data');
});

it('prices Opus 5.5 at its standard rate', function () {
    $pricing = config('player-token-usage.models.claude-opus-5-5');

    expect($pricing['periods'][0]['input'])->toBe('4.000000')
        ->and($pricing['periods'][0]['output'])->toBe('20.000000');
});

it('prices Opus 5 at the standard Opus rate', function () {
    $pricing = config('player-token-usage.models.claude-opus-5');

    expect($pricing['provider'])->toBe('anthropic')
        ->and($pricing['periods'][0]['input'])->toBe('5.000000')
        ->and($pricing['periods'][0]['output'])->toBe('25.000000');
});

it('omits sampling params for Opus 5 in the chaos runtime', function () {
    expect(ChaosEngineService::MODEL_CONFIG['claude-opus-5'])->not->toHaveKey('temperature');
});

it('registers Haiku 5.5 without sampling and keeps Haiku 4.5 as the default', function () {
    config(['prism.providers.anthropic.api_key' => 'test-anthropic-key']);
    $engine = app(ChaosEngineService::class);

    expect(ChaosEngineService::DEFAULT_MODEL)->toBe('claude-haiku-4-5')
        ->and(ChaosEngineService::MODEL_CONFIG['claude-haiku-5-5'])->not->toHaveKey('temperature')
        ->and(ChaosEngineService::MODEL_CONFIG['claude-haiku-5-5']['provider'])->toBe('anthropic')
        ->and(ChaosEngineService::MODELS_WITHOUT_SAMPLING)->toContain('claude-haiku-5-5')
        ->and(ChaosEngineService::MODELS_WITHOUT_SAMPLING)->not->toContain('claude-haiku-4-5')
        ->and($engine->gameModelFor('peter-pan'))->toBe('claude-haiku-5-5')
        ->and($engine->gameModelFor('emma'))->toBe('claude-haiku-4-5');
});
