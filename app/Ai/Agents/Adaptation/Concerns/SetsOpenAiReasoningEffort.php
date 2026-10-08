<?php

declare(strict_types=1);

namespace App\Ai\Agents\Adaptation\Concerns;

use Laravel\Ai\Enums\Lab;

/**
 * GPT-6 models reason on every request, and with reasoning on OpenAI rejects
 * temperature and top_p. Agents using this trait set their effort through
 * REASONING_EFFORT and must never carry a Temperature attribute.
 */
trait SetsOpenAiReasoningEffort
{
    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return ['reasoning' => ['effort' => self::REASONING_EFFORT]];
    }
}
