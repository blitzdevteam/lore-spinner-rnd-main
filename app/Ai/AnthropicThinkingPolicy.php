<?php

declare(strict_types=1);

namespace App\Ai;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;

/**
 * Forces `thinking.type = disabled` on outgoing Claude Opus 5 requests.
 *
 * Opus 5 runs adaptive thinking whenever the request omits a `thinking` field,
 * and thinking tokens bill as output tokens at the Opus output rate even when
 * the thinking text is never returned. Opus 4.8 ran these same agents without
 * thinking, so leaving the field off would raise the per-call output bill for
 * identical structured-JSON work.
 *
 * Prism cannot express this: its Anthropic payload only emits `thinking` when
 * the `thinking.enabled` provider option is true, and then as the deprecated
 * `{type: enabled, budget_tokens: N}` shape that Opus 4.7+ rejects with a 400.
 * There is no provider-option route to `{type: disabled}`, so the body is
 * rewritten here instead.
 *
 * Effort is deliberately left unset. Disabling thinking is only accepted at
 * effort `high` or below, and `high` is already the Opus 5 default; sending
 * `xhigh` or `max` alongside disabled thinking returns a 400 on every request.
 *
 * Scoped to Opus 5 on purpose. Earlier Anthropic models do not accept the
 * `disabled` thinking type, and every other model in the pipeline already runs
 * without thinking.
 */
final class AnthropicThinkingPolicy
{
    private const MODEL_PREFIX = 'claude-opus-5';

    public static function disableOpusThinking(RequestInterface $request): RequestInterface
    {
        if (! self::targetsAnthropicMessages($request)) {
            return $request;
        }

        $body = (string) $request->getBody();

        if ($body === '') {
            return $request;
        }

        $payload = json_decode($body, true);

        if (! is_array($payload) || isset($payload['thinking'])) {
            return $request;
        }

        $model = $payload['model'] ?? null;

        if (! is_string($model) || ! str_starts_with($model, self::MODEL_PREFIX)) {
            return $request;
        }

        $payload['thinking'] = ['type' => 'disabled'];

        $encoded = json_encode($payload);

        if ($encoded === false) {
            return $request;
        }

        return $request
            ->withBody(Utils::streamFor($encoded))
            ->withHeader('Content-Length', (string) mb_strlen($encoded, '8bit'));
    }

    private static function targetsAnthropicMessages(RequestInterface $request): bool
    {
        $uri = $request->getUri();

        return str_contains($uri->getHost(), 'anthropic')
            && str_ends_with(mb_rtrim($uri->getPath(), '/'), '/messages');
    }
}
