<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Models\Game;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\StoryAdaptation;
use App\Models\User;
use App\Services\Runtime\CompletionReceiptBuilder;

final class CompletionReceiptFixtures
{
    /**
     * @param  array<string, mixed>  $closeDesign
     * @param  array<string, mixed>  $gameAttributes
     * @param  array<string, mixed>  $sessionMap
     */
    public static function game(
        array $closeDesign = [],
        array $gameAttributes = [],
        int $sessionCount = 2,
        array $sessionMap = [],
    ): Game {
        $story = Story::factory()->create([
            'title' => 'The Adventure of the Speckled Band',
        ]);
        $user = User::factory()->withCompleteProfile()->markEmailAsVerified()->create([
            'is_active' => true,
        ]);
        $adaptation = StoryAdaptation::query()->create([
            'story_id' => $story->id,
            'adaptation_status' => AdaptationStatusEnum::COMPLETED,
            'boiler_enabled' => false,
            'story_session_map' => array_merge([
                'alignment_labels' => [
                    [
                        'label' => 'The partner who will enter the room.',
                        'maps_to_internal' => 'lawful',
                        'behavioral_markers' => ['tests before trusting'],
                        'narrative_consequences' => 'Caution becomes presence.',
                        'voice_signature' => 'Measured.',
                    ],
                    [
                        'label' => 'The one who strikes first.',
                        'maps_to_internal' => 'chaotic',
                        'behavioral_markers' => ['moves before the plan is finished'],
                        'narrative_consequences' => 'Impulse becomes action.',
                        'voice_signature' => 'Sharp.',
                    ],
                    [
                        'label' => 'The witness who withholds judgment.',
                        'maps_to_internal' => 'neutral',
                        'behavioral_markers' => ['keeps counsel'],
                        'narrative_consequences' => 'Reserve becomes authorship.',
                        'voice_signature' => 'Quiet.',
                    ],
                ],
            ], $sessionMap),
        ]);

        for ($sessionNumber = 1; $sessionNumber <= $sessionCount; $sessionNumber++) {
            SessionAdaptation::query()->create([
                'story_adaptation_id' => $adaptation->id,
                'session_number' => $sessionNumber,
                'session_status' => SessionAdaptationStatusEnum::COMPLETED,
                'boiler_status' => BoilerStatus::PENDING,
                'session_close_design' => $sessionNumber === 1
                    ? array_merge(self::sessionCloseDesign(), $closeDesign)
                    : self::storyCloseDesign(),
            ]);
        }

        $game = Game::query()->create(array_merge([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'current_session_number' => 1,
            'current_session_complete' => true,
            'model' => 'claude-sonnet-4-6',
            'alignment_scaffold' => ['chaotic' => 1, 'lawful' => 5, 'neutral' => 1],
            'defining_choice_id' => 'S1_C4',
            'defining_path_label' => 'B',
            'defining_choice_line' => 'I know how the room is meant to kill. I have not entered it yet.',
            'world_state' => [
                'location' => 'Stoke Moran',
                'knowledge' => ['The ventilator, dummy rope, and clamped bed form one trap.'],
                'relationship_updates' => ['Holmes has stopped explaining past your caution.'],
                'player_style' => ['You test before you trust the dark.'],
                'unresolved_promises' => ['Helen’s safety is not yet paid.'],
                'emotional_ledger' => [
                    ['category' => 'noise', 'entry' => 'An older, quieter fact the card should not prefer.'],
                    ['category' => 'promises_made', 'entry' => 'You sat the case into one dreadful line.'],
                    ['category' => 'npcs_helped', 'entry' => 'Helen’s middle window is lit. The signal is real.'],
                    ['category' => 'secrets', 'entry' => 'Roylott has gone into the dark of Stoke Moran.'],
                ],
            ],
            'symbolic_memory' => "A whistle in the night.\n\nA room that will never look innocent again.",
            'moment_footnote' => [
                'moment_key' => 'S1_M04',
                'text' => 'The player wants the truth to land softly.',
                'source_prompt_id' => '01h00000000000000000000000',
            ],
        ], $gameAttributes));

        return $game;
    }

    public static function loadForShow(Game $game): Game
    {
        return app(CompletionReceiptBuilder::class)->hydrate($game);
    }

    /**
     * @return array<string, mixed>
     */
    public static function sessionCloseDesign(): array
    {
        return [
            'resolution_prose' => 'You sat the case into one dreadful line. The ventilator, the dummy rope, and the clamped bed now confess the same design. Roylott has gone into the dark, and the night is no longer theory.',
            'hook_transition' => 'The lawn waits, and so does the loaded pistol.',
            'session_end_choice' => [
                'choice_question' => 'Will you enter tonight?',
                'option_a' => [
                    'text' => 'You told Holmes you would go at once.',
                    'next_session_opens' => 'Session 2 opens on the vigil itself, without another test.',
                ],
                'option_b' => [
                    'text' => 'You told Holmes you would go — but only after testing the signal once more in your mind.',
                    'next_session_opens' => 'Session 2 opens on the vigil itself — in the spirit you chose.',
                ],
                'option_c' => [
                    'text' => 'You asked Holmes to go without you.',
                    'next_session_opens' => 'Session 2 opens on the lawn while you wait for word.',
                ],
                'final_line' => 'The light waits; so, now, does Holmes.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function storyCloseDesign(): array
    {
        return [
            'resolution_prose' => 'Helen is safe. The method is known. Roylott’s design failed upon himself.',
            'hook_transition' => '',
            'session_end_choice' => [
                'choice_question' => 'How much of this may the world bear?',
                'option_a' => [
                    'text' => 'You told the whole method.',
                    'next_session_opens' => '',
                ],
                'option_b' => [
                    'text' => 'You chose a middle course: enough truth for a workable verdict.',
                    'next_session_opens' => '',
                ],
                'option_c' => [
                    'text' => 'You kept the strangest particulars from becoming Helen’s burden.',
                    'next_session_opens' => '',
                ],
                'final_line' => 'The case is solved; its meaning, and its measure, remain in your hands.',
            ],
        ];
    }
}
