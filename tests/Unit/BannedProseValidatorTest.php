<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Ai\Adaptation\BannedProseValidator;
use PHPUnit\Framework\TestCase;

final class BannedProseValidatorTest extends TestCase
{
    private BannedProseValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BannedProseValidator;
    }

    public function test_passes_clean_prose(): void
    {
        $artifact = [
            'opening_image' => 'The door opens. Cold air. A man in a suit.',
            'runtime_goal' => 'Press the player to choose between safety and truth.',
        ];

        $result = $this->validator->scan($artifact, [], ['opening_image', 'runtime_goal']);

        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_rejects_em_dash(): void
    {
        $artifact = [
            'opening_image' => 'The door opens — cold air rushes in.',
        ];

        $result = $this->validator->scan($artifact, [], ['opening_image']);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('em_dash', $result['errors'][0]);
    }

    public function test_rejects_double_hyphen(): void
    {
        $artifact = [
            'runtime_goal' => 'Press the player -- hard.',
        ];

        $result = $this->validator->scan($artifact, [], ['runtime_goal']);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('double_hyphen', $result['errors'][0]);
    }

    public function test_rejects_semicolon(): void
    {
        $artifact = [
            'live_pressure' => 'The clock ticks; the door locks.',
        ];

        $result = $this->validator->scan($artifact, [], ['live_pressure']);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('semicolon', $result['errors'][0]);
    }

    public function test_rejects_banned_vocabulary(): void
    {
        $artifact = [
            'runtime_goal' => 'Delve into the room\'s tapestry of secrets.',
        ];

        $result = $this->validator->scan($artifact, [], ['runtime_goal']);

        $this->assertNotEmpty($result['errors']);
        $this->assertTrue(
            count($result['errors']) >= 2,
            'Should catch both "delve" and "tapestry"'
        );
    }

    public function test_rejects_banned_phrase_tell(): void
    {
        $artifact = [
            'opening_image' => 'Something shifted in the room.',
        ];

        $result = $this->validator->scan($artifact, [], ['opening_image']);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('something shifted', $result['errors'][0]);
    }

    public function test_warns_on_one_simile_but_does_not_reject(): void
    {
        $artifact = [
            'live_pressure' => 'The pressure builds like a wave.',
        ];

        $result = $this->validator->scan($artifact, [], ['live_pressure']);

        $this->assertSame([], $result['errors']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_rejects_three_or_more_similes(): void
    {
        $artifact = [
            'live_pressure' => 'It rises like a wave, falls like a leaf, breaks like a branch.',
        ];

        $result = $this->validator->scan($artifact, [], ['live_pressure']);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('trailing similes', $result['errors'][0]);
    }

    public function test_scans_array_fields(): void
    {
        $artifact = [
            'source_extracts' => [
                'Clean extract.',
                'Bad — extract with em dash.',
            ],
        ];

        $result = $this->validator->scan($artifact, [], ['source_extracts.*']);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('source_extracts.1', $result['errors'][0]);
    }

    public function test_warns_on_single_word_ip_specific_ban_without_rejecting(): void
    {
        $voiceProfile = [
            'master_rule_1_hard_bans' => [
                'ip_specific_bans' => [
                    ['ban' => 'jaw in action lines'],
                ],
            ],
        ];

        $artifact = [
            'opening_image' => 'His jaw tightens.',
        ];

        $result = $this->validator->scan($artifact, $voiceProfile, ['opening_image']);

        $this->assertSame([], $result['errors']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('jaw', $result['warnings'][0]);
    }

    public function test_rejects_multi_word_ip_specific_ban(): void
    {
        $voiceProfile = [
            'master_rule_1_hard_bans' => [
                'ip_specific_bans' => [
                    ['ban' => 'jaw clenched in action lines'],
                ],
            ],
        ];

        $artifact = [
            'opening_image' => 'His jaw clenched at the door.',
        ];

        $result = $this->validator->scan($artifact, $voiceProfile, ['opening_image']);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('jaw clenched', $result['errors'][0]);
    }

    /**
     * A ban phrased "Holmes in narration" must never become a hard gate — the name is
     * unavoidable in the prose, so rejecting on it would fail every draft in the title.
     */
    public function test_proper_noun_ip_specific_ban_never_blocks(): void
    {
        $voiceProfile = [
            'master_rule_1_hard_bans' => [
                'ip_specific_bans' => [
                    ['ban' => 'Holmes in narration'],
                    ['ban' => 'fog as atmosphere'],
                ],
            ],
        ];

        $artifact = [
            'opening_image' => 'Holmes steps into the fog.',
        ];

        $result = $this->validator->scan($artifact, $voiceProfile, ['opening_image']);

        $this->assertSame([], $result['errors']);
    }

    /**
     * Voice Lock writes bans as full sentences, so extraction yields fragments such as
     * "Do not write grief". Those must not be enforced as literal terms.
     */
    public function test_ignores_instruction_fragment_bans(): void
    {
        $voiceProfile = [
            'master_rule_1_hard_bans' => [
                'ip_specific_bans' => [
                    ['ban' => 'Do not write grief as overt sobbing monologue or sentimental confession.'],
                ],
            ],
        ];

        $artifact = [
            'opening_image' => 'Do not write grief here.',
        ];

        $result = $this->validator->scan($artifact, $voiceProfile, ['opening_image']);

        $this->assertSame([], $result['errors']);
    }

    public function test_neutralize_banned_punctuation_clears_hard_ban_marks(): void
    {
        $cleaned = BannedProseValidator::neutralizeBannedPunctuation(
            'The gate holds; she waits — then the book opens...'
        );

        $result = $this->validator->scan(
            ['next_bridge' => $cleaned],
            [],
            ['next_bridge'],
        );

        $this->assertSame([], $result['errors']);
        $this->assertStringNotContainsString(';', $cleaned);
        $this->assertStringNotContainsString('—', $cleaned);
        $this->assertStringNotContainsString('...', $cleaned);
    }

    public function test_neutralizes_every_prose_string_but_leaves_identifiers_alone(): void
    {
        $artifact = BannedProseValidator::neutralizeArtifact([
            'branching_choices' => [[
                'choice_id' => 'S4_C2',
                'moment_key' => 'S4_M04',
                'options' => [[
                    'label' => 'A',
                    'outcome' => 'The cup rises; the fairy does not wait — she leaps.',
                    'environmental_flags' => ['hook_personal_enemy_felt'],
                ]],
            ]],
        ]);

        $option = $artifact['branching_choices'][0]['options'][0];

        $this->assertSame('The cup rises. The fairy does not wait, she leaps.', $option['outcome']);
        $this->assertSame('S4_C2', $artifact['branching_choices'][0]['choice_id']);
        $this->assertSame('S4_M04', $artifact['branching_choices'][0]['moment_key']);
        $this->assertSame(['hook_personal_enemy_felt'], $option['environmental_flags']);
    }

    public function test_ignores_empty_and_missing_fields(): void
    {
        $artifact = [
            'opening_image' => '',
            'runtime_goal' => null,
        ];

        $result = $this->validator->scan($artifact, [], ['opening_image', 'runtime_goal', 'missing_field']);

        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['warnings']);
    }
}
