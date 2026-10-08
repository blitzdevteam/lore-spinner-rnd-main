<?php

declare(strict_types=1);

namespace App\Ai\Agents\Adaptation;

use App\Ai\Agents\Adaptation\Concerns\CachesAnthropicPrompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;
use Throwable;

/**
 * Voice Lock for novels in one pass: the whole book in a single long-context
 * read, with exact prose statistics measured in PHP supplied as facts.
 *
 * Same system prompt and the same final schema as VoiceLockMergeAgent, so the
 * voice profile every later phase reads keeps its exact shape.
 */
#[Provider('anthropic')]
#[Model('claude-opus-5-5')]
#[MaxTokens(64000)]
#[Timeout(1080)]
final class VoiceLockBookAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use CachesAnthropicPrompt, Promptable;

    /**
     * @param  array<string, mixed>  $formatDetection
     * @param  array<string, mixed>  $ipAudit
     */
    public function __construct(
        private ?string $detectedFormat = null,
        private array $formatDetection = [],
        private array $ipAudit = [],
    ) {}

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.voice-lock.system-prompt-novelist', [
            'formatDetection' => $this->formatDetection,
            'formatDetectionOutput' => json_encode($this->formatDetection, JSON_PRETTY_PRINT),
            'ipAudit' => $this->ipAudit,
            'currentPhase' => 'Voice Lock Phase: Whole-Book Synthesis',
        ])->render();
    }

    public function schema(JsonSchema $schema): array
    {
        return VoiceLockSchema::mergeSchema($schema, $this->detectedFormat);
    }
}
