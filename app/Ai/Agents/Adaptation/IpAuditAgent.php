<?php

declare(strict_types=1);

namespace App\Ai\Agents\Adaptation;

use App\Ai\Agents\Adaptation\Concerns\SetsOpenAiReasoningEffort;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;
use Throwable;

#[Model('gpt-6.1-sol')]
#[Timeout(180)]
final class IpAuditAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable, SetsOpenAiReasoningEffort;

    private const string REASONING_EFFORT = 'medium';

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.ip-audit.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        $criterionSchema = $schema->object([
            'score' => $schema->number()->required()->title('Score')->description('Score from 1-3.'),
            'evidence' => $schema->string()->required()->title('Evidence')->description('2-4 sentences of specific evidence. Quote from source where possible.'),
        ])->required()->withoutAdditionalProperties();

        return [
            'licensing_friction' => (clone $criterionSchema)->title('Licensing Friction'),
            'latent_choice_architecture' => (clone $criterionSchema)->title('Latent Choice Architecture'),
            'bounded_agency' => (clone $criterionSchema)->title('Bounded Agency'),
            'emotional_range' => (clone $criterionSchema)->title('Emotional Range'),
            'recognizability' => (clone $criterionSchema)->title('Recognizability Coefficient'),
            'replayability_hook' => (clone $criterionSchema)->title('Replayability Hook'),
            'total_score' => $schema
                ->number()
                ->required()
                ->title('Total Score')
                ->description('Sum of all six criterion scores. Maximum 18.'),
            'verdict' => $schema
                ->string()
                ->required()
                ->title('Verdict')
                ->description('GREEN (15-18), AMBER (10-14), or RED (below 10).'),
            'lowest_scoring_criterion' => $schema
                ->string()
                ->required()
                ->title('Lowest Scoring Criterion')
                ->description('Name of the criterion with the lowest score.'),
            'editorial_mitigation' => $schema
                ->string()
                ->required()
                ->title('Editorial Mitigation')
                ->description('One paragraph on how to address the weakest criterion before Phase 2 begins.'),
        ];
    }
}
