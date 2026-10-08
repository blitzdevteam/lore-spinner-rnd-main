<?php

declare(strict_types=1);

namespace App\Ai\Agents\Adaptation\Concerns;

use Laravel\Ai\Enums\Lab;

/**
 * Anthropic automatic prompt caching. The system prompt and the structured
 * output schema are identical for every session's call to the same agent, so
 * repeated reads bill at the cache rate. Prompts below the cache minimum are
 * simply not cached.
 */
trait CachesAnthropicPrompt
{
    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return ['cache_control' => ['type' => 'ephemeral']];
    }
}
