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

/**
 * Reviews one finalized session's stored story material against the source,
 * the author's voice, and the narrator's own rules, and proposes text patches.
 *
 * Patches only replace existing prose fields, and rebinds only move an
 * authored choice between cards. StoryRepairPatchApplier enforces both.
 */
#[Provider('anthropic')]
#[Model('claude-opus-5-5')]
#[Timeout(840)]
final class StoryRepairAgent implements Agent, HasStructuredOutput
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
        return view('ai.agents.adaptation.story-repair.system-prompt', $this->context)->render();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'findings' => $schema->array()->required()->title('Findings')
                ->description('Every defect found, including ones not patched. Empty only if the material is clean.')
                ->items($schema->object([
                    'severity' => $schema->string()->required()->description('critical, major, or minor.'),
                    'category' => $schema->string()->required()->description('contradiction, canon, pov, voice, slop, choice, consequence, continuity, or character.'),
                    'location' => $schema->string()->required()->description('Target and path, e.g. scene_card S4_M02 opening_image.'),
                    'issue' => $schema->string()->required()->description('What is wrong and the evidence for it, quoting the material.'),
                ])->required()->withoutAdditionalProperties()),
            'patches' => $schema->array()->required()->title('Patches')
                ->description('Replacements for existing text fields. One patch per field.')
                ->items($schema->object([
                    'target' => $schema->string()->required()->description('scene_card, session_choice_design, choice_consequence_map, session_close_design, or entry_point_diagnosis.'),
                    'key' => $schema->string()->required()->description('The moment_key for scene_card patches. Empty string for every other target.'),
                    'path' => $schema->string()->required()->description('Dot path to an existing text field inside the target, list indexes as numbers, e.g. branching_choices.0.options.2.text.'),
                    'new_value' => $schema->string()->required()->description('The complete replacement text for that field.'),
                    'reason' => $schema->string()->required()->description('Which finding this fixes.'),
                ])->required()->withoutAdditionalProperties()),
            'reorder' => $schema->array()->required()->title('Reorder')
                ->description('Empty unless the cards are out of story order. Otherwise the complete list of every moment_key in the new order, closing card last. Every card whose next card changes needs a next_bridge patch, and a new first card needs a cold_open patch.')
                ->items($schema->string()->required()),
            'rebinds' => $schema->array()->required()->title('Rebinds')
                ->description('Authored choices attached to the wrong card, moved to the card whose scene they belong to. Empty when every choice is on its scene.')
                ->items($schema->object([
                    'choice_id' => $schema->string()->required()->description('The authored choice id, exactly as stored.'),
                    'from_moment_key' => $schema->string()->required()->description('The card that carries it now.'),
                    'to_moment_key' => $schema->string()->required()->description('The card whose scene the choice belongs to.'),
                    'reason' => $schema->string()->required()->description('Which finding this fixes, with the evidence.'),
                ])->required()->withoutAdditionalProperties()),
        ];
    }
}
