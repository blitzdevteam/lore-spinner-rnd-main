<?php

declare(strict_types=1);

namespace App\Ai\Agents\Adaptation;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;
use Throwable;

#[Provider('anthropic')]
#[Model('claude-opus-5')]
#[Timeout(600)]
final class BoilerFinalizeAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.boiler-finalize.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        $eventRole = $schema->object([
            'event_id' => $schema->integer()->required()->title('Event ID'),
            'primary_role' => $schema->string()->required()->title('Primary Role'),
            'included_moment_key' => $schema->string()->required()->title('Included Moment Key'),
            'why' => $schema->string()->required()->title('Why'),
            'reintroduction_note' => $schema->string()->required()->title('Reintroduction Note'),
        ])->required()->withoutAdditionalProperties();

        $sceneCard = $schema->object([
            'moment_key' => $schema->string()->required()->title('Moment Key'),
            'moment_order' => $schema->integer()->required()->title('Moment Order'),
            'title' => $schema->string()->required()->title('Title'),
            'source_event_ids' => $schema->array()->required()->title('Source Event IDs')
                ->items($schema->integer()->required()),
            'anchor_event_id' => $schema->integer()->required()->title('Anchor Event ID'),
            'treatment' => $schema->string()->required()->title('Treatment'),
            'runtime_goal' => $schema->string()->required()->title('Runtime Goal'),
            'player_desire' => $schema->string()->required()->title('Player Desire'),
            'live_pressure' => $schema->string()->required()->title('Live Pressure'),
            'opening_image' => $schema->string()->required()->title('Opening Image'),
            'player_affordances' => $schema->array()->required()->title('Player Affordances')
                ->items($schema->string()->required()),
            'canon_must_keep' => $schema->array()->required()->title('Canon Must Keep')
                ->items($schema->string()->required()),
            'allowed_variance' => $schema->array()->required()->title('Allowed Variance')
                ->items($schema->string()->required()),
            'forbidden_expansions' => $schema->array()->required()->title('Forbidden Expansions')
                ->items($schema->string()->required()),
            'source_extracts' => $schema->array()->required()->title('Source Extracts')
                ->items($schema->string()->required()),
            'choice_ids' => $schema->array()->required()->title('Choice IDs')
                ->description('Exactly all branching_choices and emotional_choices whose moment_key equals this card moment_key; no choices bound elsewhere and no posture shift IDs.')
                ->items($schema->string()->required()),
            'word_budget' => $schema->object([
                'target_per_turn' => $schema->integer()->required()->title('Target Words Per Turn')
                    ->description('Whole number of 1 or more.'),
            ])->required()->withoutAdditionalProperties(),
            'turn_budget' => $schema->object([
                'target_turns' => $schema->integer()->required()->title('Target Turns')
                    ->description('Whole number of 1 or more: how many turns this room should ideally take.'),
                'gravity_after' => $schema->integer()->required()->title('Gravity After')
                    ->description('The turn count after which the runtime starts pushing toward the exit. It must be greater than or equal to target_turns, never less; equal means pressure begins the moment the target is reached.'),
            ])->required()->withoutAdditionalProperties(),
            'exit_condition' => $schema->string()->required()->title('Semantic Exit Condition'),
            'next_moment_key' => $schema->string()->required()->title('Next Moment Key')
                ->description('The next sequential key for non-final moments; empty string for the final moment.'),
            'next_bridge' => $schema->string()->required()->title('Next Bridge'),
        ])->required()->withoutAdditionalProperties();

        return [
            'boiler_version' => $schema->string()->required()->title('Boiler Version'),
            'session_number' => $schema->integer()->required()->title('Session Number'),
            'cut_key' => $schema->string()->required()->title('Cut Key'),
            'playable_promise' => $schema->string()->required()->title('Playable Promise'),
            'moments' => $schema->array()->required()->title('Final Ordered Scene Cards')->items($sceneCard),
            'event_role_map' => $schema->array()->required()->title('Final Event Role Map')->items($eventRole),
        ];
    }
}
