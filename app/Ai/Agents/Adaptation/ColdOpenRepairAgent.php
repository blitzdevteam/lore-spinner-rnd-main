<?php

declare(strict_types=1);

namespace App\Ai\Agents\Adaptation;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;
use Throwable;

/**
 * Rewrites one story's cold open from stored diagnosis and voice.
 *
 * Separate from EntryPointDiagnosisAgent. The pipeline agent stays as it is
 * until a repaired opening is greenlit.
 */
#[Provider('anthropic')]
#[Model('claude-sonnet-4-6')]
#[Temperature(0.6)]
#[Timeout(180)]
final class ColdOpenRepairAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(private array $context) {}

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.cold-open-repair.system-prompt', $this->context)->render();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'cold_open' => $schema
                ->string()
                ->required()
                ->title('Cold Open')
                ->description(
                    'Player-facing opening prose only. Second person, present tense, about 300 words and never more than 350. '
                    .'Name the protagonist within the first 60 words. State the place, the people present, and the strange event in plain words. '
                    .'End on the action that poses the first choice. Do not write a question to the player. '
                    .'No title, no session label, no === markers, no option list.'
                ),
        ];
    }
}
