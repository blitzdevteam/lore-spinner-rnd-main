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
final class BoilerDraftAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.boiler-draft.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        $eventRole = $schema->object([
            'event_id' => $schema->integer()->required()->title('Event ID'),
            'role' => $schema->string()->required()->title('Role')
                ->description('PLAY / PULSE / CHOICE / REACTION / MONTAGE / ECHO / CUT'),
            'why' => $schema->string()->required()->title('Why'),
            'included_moment_key' => $schema->string()->required()->title('Included Moment Key')
                ->description('S{session}_M{nn}, or empty string when not directly included.'),
            'reintroduction_note' => $schema->string()->required()->title('Reintroduction Note')
                ->description('Required when compressed/cut canon or earned effect must reappear; otherwise empty string.'),
        ])->required()->withoutAdditionalProperties();

        $momentDraft = $schema->object([
            'moment_key' => $schema->string()->required()->title('Moment Key')
                ->description('Exact contiguous key S{session_number}_M{two-digit order}, for example S2_M01.'),
            'moment_order' => $schema->integer()->required()->title('Moment Order'),
            'source_event_ids' => $schema->array()->required()->title('Source Event IDs')
                ->description('A strict partition: each event ID may occur in at most one moment_drafts source_event_ids list.')
                ->items($schema->integer()->required()),
            'anchor_event_id' => $schema->integer()->required()->title('Anchor Event ID'),
            'treatment' => $schema->string()->required()->title('Treatment'),
            'runtime_goal' => $schema->string()->required()->title('Runtime Goal'),
            'player_desire' => $schema->string()->required()->title('Player Desire'),
            'live_pressure' => $schema->string()->required()->title('Live Pressure'),
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
            'exit_condition_draft' => $schema->string()->required()->title('Semantic Exit Condition Draft'),
        ])->required()->withoutAdditionalProperties();

        return [
            'boiler_version' => $schema->string()->required()->title('Boiler Version'),
            'session_number' => $schema->integer()->required()->title('Session Number'),
            'playable_promise' => $schema->string()->required()->title('Playable Promise'),
            'pacing_guidance_used' => $schema->object([
                'compaction_level' => $schema->string()->required()->title('Compaction Level'),
                'target_playtime_minutes' => $schema->integer()->required()->title('Target Playtime Minutes'),
                'recommended_moment_count_min' => $schema->integer()->required()->title('Moment Count Minimum'),
                'recommended_moment_count_max' => $schema->integer()->required()->title('Moment Count Maximum'),
            ])->required()->withoutAdditionalProperties(),
            'session_budget_decision' => $schema->object([
                'target_playtime_minutes' => $schema->integer()->required()->title('Target Playtime Minutes'),
                'target_moment_count' => $schema->integer()->required()->title('Target Moment Count'),
                'budget_reasoning' => $schema->string()->required()->title('Budget Reasoning'),
                'must_compress_event_ids' => $schema->array()->required()->title('Must Compress Event IDs')
                    ->items($schema->integer()->required()),
                'must_breathe_event_ids' => $schema->array()->required()->title('Must Breathe Event IDs')
                    ->items($schema->integer()->required()),
            ])->required()->withoutAdditionalProperties(),
            'playable_promise_arc' => $schema->object([
                'first_proof_moment' => $schema->string()->required()->title('First Proof Moment')
                    ->description('Copy one complete moment_drafts[].moment_key exactly. Never use a title, event ID, shorthand, or undrafted key.'),
                'midpoint_intensification' => $schema->string()->required()->title('Midpoint Intensification')
                    ->description('Copy one complete moment_drafts[].moment_key exactly. It may equal another arc key in a short session.'),
                'final_cash_out' => $schema->string()->required()->title('Final Cash Out')
                    ->description('Copy the final ordered moment_drafts[].moment_key exactly.'),
            ])->required()->withoutAdditionalProperties(),
            'event_role_map' => $schema->array()->required()->title('Event Role Map')->items($eventRole),
            'moment_drafts' => $schema->array()->required()->title('Moment Drafts')->items($momentDraft),
            'cut_material' => $schema->array()->required()->title('Cut Material')->items(
                $schema->object([
                    'event_ids' => $schema->array()->required()->title('Event IDs')
                        ->items($schema->integer()->required()),
                    'reason' => $schema->string()->required()->title('Reason'),
                    'reintroduction_route' => $schema->string()->required()->title('Reintroduction Route'),
                ])->required()->withoutAdditionalProperties()
            ),
            'coverage_check' => $schema->object([
                'every_event_has_one_role' => $schema->boolean()->required()->title('Every Event Has One Role'),
                'all_required_canon_routed' => $schema->boolean()->required()->title('All Required Canon Routed'),
                'first_agency_is_early' => $schema->boolean()->required()->title('First Agency Is Early'),
            ])->required()->withoutAdditionalProperties(),
        ];
    }
}
