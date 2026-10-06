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
 * Pipeline Upgrade V2 — IP Trimming (per-chapter pass).
 *
 * Processes ONE chapter at a time. Produces a partial Deliverable 7 fragment
 * containing the chapter's story-spine contributions, world rules observed,
 * content triage log, interactive conversion notes, and trimmed chapter text.
 *
 * All fragments are collected by IpTrimmingMergeJob which:
 *   1. PHP-merges world_rules, triage_log, conversion_notes, and trimmed text.
 *   2. Makes a single small synthesis API call (IpTrimmingMergeAgent) to produce
 *      the unified story_spine from all chapter spine fragments.
 *   3. Writes the final Deliverable 7 package to story_adaptations.ip_trimming.
 *
 * claude-opus-5-5 via Anthropic: precise editorial triage requiring nuanced
 * PRESERVE/TRIM judgment and verbatim prose reconstruction. Opus 5.5 always
 * thinks; AnthropicThinkingPolicy keeps its requests in the accepted shape.
 *
 * Prism v0.99.21+ (composer.lock updated) fixes the HTTP 400 that was caused by
 * the old NativeOutputFormatStructuredStrategy sending the deprecated `output_format`
 * field. v0.99.22 sends `output_config.format` per the GA Anthropic API.
 * No beta header (structured-outputs-2025-11-13) is needed — GA structured output
 * is now the default in v0.100.0+.
 */
#[Provider('anthropic')]
#[Model('claude-opus-5-5')]
#[Timeout(600)]
final class IpTrimmingChapterAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.ip-trimming.chapter-system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        $turningPointSchema = $schema->object([
            'reference' => $schema->string()->required()->title('Reference')->description('Chapter or scene reference, e.g. "Ch.3 Scene 2".'),
            'description' => $schema->string()->required()->title('Description')->description('One sentence: what happens and why it matters narratively.'),
        ])->required()->withoutAdditionalProperties();

        $worldRuleSchema = $schema->object([
            'rule' => $schema->string()->required()->title('Rule'),
            'evidence' => $schema->string()->required()->title('Evidence')->description('Chapter/scene reference where this rule is established.'),
        ])->required()->withoutAdditionalProperties();

        $triageEntrySchema = $schema->object([
            'reference' => $schema->string()->required()->title('Reference')->description('Scene or passage identifier within this chapter.'),
            'classification' => $schema->string()->required()->title('Classification')->description('PRESERVE or TRIM.'),
            'note' => $schema->string()->required()->title('Note')->description('One sentence: what it contains and why preserved or trimmed.'),
        ])->required()->withoutAdditionalProperties();

        $conversionNoteSchema = $schema->object([
            'reference' => $schema->string()->required()->title('Reference'),
            'original_content_summary' => $schema->string()->required()->title('Original Content Summary'),
            'conversion_type' => $schema->string()->required()->title('Conversion Type')->description('EXPLORABLE ENVIRONMENT / NPC DIALOGUE / DISCOVERABLE LORE / WORLD-EXPLORATION REWARD / EMOTIONAL DISCOVERY.'),
            'conversion_instruction' => $schema->string()->required()->title('Conversion Instruction'),
            'information_to_preserve' => $schema->string()->required()->title('Information To Preserve'),
        ])->required()->withoutAdditionalProperties();

        return [
            'chapter_id' => $schema->number()->required()->title('Chapter ID')->description('The chapter ID as provided in the prompt.'),

            'chapter_position' => $schema->number()->required()->title('Chapter Position')->description('The chapter position (1-based) as provided in the prompt.'),

            'story_spine_fragment' => $schema->object([
                'protagonist' => $schema->string()->required()->title('Protagonist')->description('Name and one-sentence description ONLY if first introduced in this chapter. Empty string otherwise.'),
                'dramatic_question' => $schema->string()->required()->title('Dramatic Question')->description('The story\'s dramatic question AS REFORMULATED from this chapter\'s content. Empty string if this chapter doesn\'t clarify the dramatic question.'),
                'major_turning_points' => $schema->array()->required()->title('Major Turning Points')->description('Turning points that occur within this chapter only.')->items($turningPointSchema),
                'irreversible_events' => $schema->array()->required()->title('Irreversible Events')->description('Events within this chapter that cannot be player choices because they define the world state going forward.')->items(
                    $schema->object([
                        'event' => $schema->string()->required()->title('Event'),
                        'why_fixed' => $schema->string()->required()->title('Why Fixed'),
                    ])->required()->withoutAdditionalProperties()
                ),
                'climax_fragment' => $schema->string()->required()->title('Climax Fragment')->description('If the story\'s climax occurs in this chapter, describe it in one sentence. Empty string otherwise.'),
                'resolution_fragment' => $schema->string()->required()->title('Resolution Fragment')->description('If the story\'s resolution occurs in this chapter, describe it in one sentence. Empty string otherwise.'),
            ])->required()->withoutAdditionalProperties()->title('Story Spine Fragment'),

            'world_rules_fragments' => $schema->object([
                'physics_technology' => $schema->array()->required()->title('Physics / Technology')->items($worldRuleSchema),
                'creatures_entities' => $schema->array()->required()->title('Creatures / Entities')->items($worldRuleSchema),
                'geography_locations' => $schema->array()->required()->title('Geography / Locations')->items($worldRuleSchema),
                'social_systems' => $schema->array()->required()->title('Social Systems')->items($worldRuleSchema),
                'what_cannot_exist' => $schema->array()->required()->title('What Cannot Exist')->items(
                    $schema->object([
                        'thing' => $schema->string()->required()->title('Thing'),
                        'why' => $schema->string()->required()->title('Why'),
                    ])->required()->withoutAdditionalProperties()
                ),
            ])->required()->withoutAdditionalProperties()->title('World Rules Fragments'),

            'content_triage_log' => $schema->array()->required()->title('Content Triage Log')->description('Every scene and passage in this chapter must be classified.')->items($triageEntrySchema),

            'interactive_conversion_notes' => $schema->array()->required()->title('Interactive Conversion Notes')->description('One entry for every TRIM entry in the triage log.')->items($conversionNoteSchema),

            'trimmed_chapter_text' => $schema->string()->required()->title('Trimmed Chapter Text')->description('Complete trimmed chapter content with TRIM MARKER lines at each cut point. All preserved dialogue and character-action prose must appear verbatim. Do NOT rewrite or paraphrase preserved content.'),
        ];
    }
}
