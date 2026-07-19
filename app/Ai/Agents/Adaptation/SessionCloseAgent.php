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

#[Model('gpt-5.4')]
#[Temperature(0.7)]
#[Timeout(180)]
class SessionCloseAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(private bool $boilerEnabled = false) {}

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.session-close.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'session_close_trigger_event_position' => $schema
                ->integer()
                ->required()
                ->title('Session Close Trigger Event Position')
                ->description('The story_position (1-based story-global ordinal) of the exact event from the provided event list where the session close fires. This is the event the player is on when the resolution prose and session-end choice must be delivered. Pick an actual event from the list — not an abstraction. This is an authored exit-point decision, the exit-side counterpart to start_event_position.'),
            'resolution_prose' => $schema
                ->string()
                ->required()
                ->title('Resolution Prose')
                ->description('120-200 words, second-person present tense. Sensory specificity. Real unambiguous payoff.'),
            'hook_transition' => $schema
                ->string()
                ->required()
                ->title('Hook Transition')
                ->description('2-3 sentences transitioning from resolution to the session-end hook choice.'),
            'session_end_choice' => $schema->object([
                'choice_question' => $schema->string()->required()->title('Choice Question'),
                'option_a' => $schema->object([
                    'text' => $schema->string()->required()->title('Option Text'),
                    'next_session_opens' => $schema->string()->required()->title('Next Session Opens'),
                ])->required()->withoutAdditionalProperties()->title('Option A'),
                'option_b' => $schema->object([
                    'text' => $schema->string()->required()->title('Option Text'),
                    'next_session_opens' => $schema->string()->required()->title('Next Session Opens'),
                ])->required()->withoutAdditionalProperties()->title('Option B'),
                'option_c' => $schema->object([
                    'text' => $schema->string()->required()->title('Option Text'),
                    'next_session_opens' => $schema->string()->required()->title('Next Session Opens'),
                ])->required()->withoutAdditionalProperties()->title('Option C'),
                'final_line' => $schema->string()->required()->title('Final Line')->description('The sessions last words. An invitation, not a cliffhanger.'),
            ])->required()->withoutAdditionalProperties()->title('Session-End Choice'),
            ...($this->boilerEnabled ? [
                'closing_moment_key' => $schema->string()->required()->title('Boiler Closing Moment Key'),
                'closing_moment_relationship' => $schema->string()->required()->title('Close Trigger Relationship')
                    ->description('How the existing trigger event and close resolve inside the final ordered moment.'),
                'next_bridge_guidance' => $schema->string()->required()->title('Next Bridge Guidance')
                    ->description('Guidance for Finalize to close the moment and hand off to the next session.'),
            ] : []),
            'stickiness_audit' => $schema->object([
                'payoff_test' => $schema->string()->required()->title('Payoff Test')->description('PASS or REVISE.'),
                'return_driver_test' => $schema->string()->required()->title('Return Driver Test')->description('PASS or REVISE.'),
                'overnight_test' => $schema->string()->required()->title('Overnight Test')->description('PASS or REVISE.'),
            ])->required()->withoutAdditionalProperties()->title('Stickiness Audit'),
        ];
    }
}
