<?php

declare(strict_types=1);

namespace App\Ai\Agents\Adaptation;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;
use Throwable;

/**
 * Pipeline Upgrade V2 — IP Trimming (story-spine synthesis pass).
 *
 * Receives the story_spine_fragments from every chapter and produces a single
 * unified story_spine matching the Deliverable 7 schema. This is the ONLY API
 * call in IpTrimmingMergeJob — all other merges (world_rules deduplication,
 * triage log concatenation, conversion notes concatenation, trimmed text
 * assembly) are performed in PHP before this call.
 *
 * Input payload is small (spine fragments only, no prose), so gpt-5.4 at
 * default temperature is appropriate: synthesis requires coherent judgment
 * across fragments, but the output is a deterministic structural summary.
 */
#[Model('gpt-5.4')]
#[Temperature(0.3)]
#[Timeout(120)]
final class IpTrimmingMergeAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.ip-trimming.merge-system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        $turningPointSchema = $schema->object([
            'reference' => $schema->string()->required()->title('Reference')->description('Chapter or scene reference.'),
            'description' => $schema->string()->required()->title('Description')->description('One sentence: what happens and why it matters.'),
        ])->required()->withoutAdditionalProperties();

        return [
            'protagonist' => $schema->string()->required()->title('Protagonist')->description('Name and one sentence: who they are at the start of the story.'),

            'dramatic_question' => $schema->string()->required()->title('Dramatic Question')->description('One sentence: what the story asks.'),

            'world' => $schema->string()->required()->title('World')->description('One sentence: where and when, plus the single most important world rule.'),

            'major_turning_points' => $schema->array()->required()->title('Major Turning Points')->description('All major turning points in chronological order across all chapters.')->items($turningPointSchema),

            'climax' => $turningPointSchema->title('Climax'),

            'resolution' => $turningPointSchema->title('Resolution'),

            'irreversible_events' => $schema->array()->required()->title('Irreversible Events')->description('Events that cannot be player choices because they define the world state.')->items(
                $schema->object([
                    'event' => $schema->string()->required()->title('Event'),
                    'why_fixed' => $schema->string()->required()->title('Why Fixed'),
                ])->required()->withoutAdditionalProperties()
            ),
        ];
    }
}
