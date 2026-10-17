<?php

declare(strict_types=1);

namespace App\Ai;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;

/**
 * Rewrites outgoing Claude requests into the shape each model accepts.
 *
 * Opus 5 runs adaptive thinking whenever the request omits a `thinking` field,
 * and thinking tokens bill as output tokens at the Opus output rate even when
 * the thinking text is never returned. Opus 4.8 ran these same agents without
 * thinking, so Opus 5 requests get `thinking.type = disabled`. Prism cannot
 * express that shape, so the body is rewritten here instead. Effort is left
 * unset: disabling thinking is only accepted at effort `high` or below.
 *
 * Opus 5.5 is different. Thinking is always on and `disabled` returns a 400,
 * forced tool use (`tool_choice` of `tool` or `any`) returns an error, and
 * non-default sampling parameters are rejected. The SDK forces its synthetic
 * structured-output tool, and some agents carry a temperature attribute, so
 * Opus 5.5 requests get `tool_choice = auto` and no sampling parameters. With
 * the structured-output tool as the only tool, the model still calls it, and
 * the SDK falls back to parsing JSON text when it does not.
 *
 * Haiku 5.5 supports forced tools with adaptive thinking, but a forced tool
 * starts directly with the tool call. Production narration deliberately keeps
 * that behaviour for streaming. Sampling parameters are omitted because the
 * model rejects non-default sampling alongside adaptive thinking.
 */
final class AnthropicThinkingPolicy
{
    private const OPUS_5_PREFIX = 'claude-opus-5';

    private const OPUS_5_5_PREFIX = 'claude-opus-5-5';

    private const HAIKU_5_5 = 'claude-haiku-5-5';

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

        if (! is_array($payload)) {
            return $request;
        }

        $model = $payload['model'] ?? null;

        if (! is_string($model)) {
            return $request;
        }

        $rewritten = match (true) {
            $model === self::HAIKU_5_5 => self::forHaiku55($payload),
            str_starts_with($model, self::OPUS_5_5_PREFIX) => self::forOpus55($payload),
            str_starts_with($model, self::OPUS_5_PREFIX) => self::forOpus5($payload),
            default => null,
        };

        if ($rewritten === null) {
            return $request;
        }

        $encoded = json_encode($rewritten);

        if ($encoded === false) {
            return $request;
        }

        return $request
            ->withBody(Utils::streamFor($encoded))
            ->withHeader('Content-Length', (string) mb_strlen($encoded, '8bit'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private static function forOpus5(array $payload): ?array
    {
        if (isset($payload['thinking'])) {
            return null;
        }

        $payload['thinking'] = ['type' => 'disabled'];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private static function forOpus55(array $payload): ?array
    {
        $changed = false;

        $thinkingType = $payload['thinking']['type'] ?? null;
        if (isset($payload['thinking']) && $thinkingType !== 'adaptive') {
            unset($payload['thinking']);
            $changed = true;
        }

        $toolChoiceType = $payload['tool_choice']['type'] ?? null;
        if (in_array($toolChoiceType, ['tool', 'any'], true)) {
            $forcedTool = $toolChoiceType === 'tool' ? (string) ($payload['tool_choice']['name'] ?? '') : '';
            $payload['tool_choice'] = ['type' => 'auto'];
            $changed = true;

            if ($forcedTool !== '') {
                $payload = self::withSystemLine(
                    $payload,
                    "Respond only by calling the {$forcedTool} tool with the complete result. Do not answer in plain text.",
                );
            }
        }

        foreach (['temperature', 'top_p', 'top_k'] as $samplingParameter) {
            if (array_key_exists($samplingParameter, $payload)) {
                unset($payload[$samplingParameter]);
                $changed = true;
            }
        }

        return $changed ? $payload : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private static function forHaiku55(array $payload): ?array
    {
        $changed = false;

        foreach (['temperature', 'top_p', 'top_k'] as $samplingParameter) {
            if (array_key_exists($samplingParameter, $payload)) {
                unset($payload[$samplingParameter]);
                $changed = true;
            }
        }

        return $changed ? $payload : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function withSystemLine(array $payload, string $line): array
    {
        $system = $payload['system'] ?? null;

        if (is_array($system)) {
            $system[] = ['type' => 'text', 'text' => $line];
            $payload['system'] = $system;

            return $payload;
        }

        $payload['system'] = is_string($system) && $system !== '' ? $system."\n\n".$line : $line;

        return $payload;
    }

    private static function targetsAnthropicMessages(RequestInterface $request): bool
    {
        $uri = $request->getUri();

        return str_contains($uri->getHost(), 'anthropic')
            && str_ends_with(mb_rtrim($uri->getPath(), '/'), '/messages');
    }
}
