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
})->with(['claude-opus-4-8', 'claude-opus-4-7', 'claude-sonnet-4-6', 'claude-haiku-4-5']);

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

it('pins IP Trimming to Opus 5', function () {
    $reflection = new ReflectionClass(IpTrimmingChapterAgent::class);

    expect($reflection->getAttributes(Provider::class)[0]->getArguments()[0])->toBe('anthropic')
        ->and($reflection->getAttributes(Model::class)[0]->getArguments()[0])->toBe('claude-opus-5');
});

// Opus 5 with thinking disabled returned schema-noncompliant Boiler drafts, so
// drafting and finalizing stay on the model that produced valid cuts across the
// launch titles. The thinking policy above is scoped by model name, so it simply
// stops applying to these two agents.
it('keeps the Boiler agents off Opus 5 until it is proven on a full session', function (string $agent) {
    $reflection = new ReflectionClass($agent);

    expect($reflection->getAttributes(Model::class)[0]->getArguments()[0])->toBe('claude-opus-4-8');
})->with([BoilerDraftAgent::class, BoilerFinalizeAgent::class]);

it('prices Opus 5 at the standard Opus rate', function () {
    $pricing = config('player-token-usage.models.claude-opus-5');

    expect($pricing['provider'])->toBe('anthropic')
        ->and($pricing['periods'][0]['input'])->toBe('5.000000')
        ->and($pricing['periods'][0]['output'])->toBe('25.000000');
});

it('omits sampling params for Opus 5 in the chaos runtime', function () {
    expect(ChaosEngineService::MODEL_CONFIG['claude-opus-5'])->not->toHaveKey('temperature');
});
