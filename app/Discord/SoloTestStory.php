<?php

declare(strict_types=1);

namespace App\Discord;

final class SoloTestStory
{
    public const string COMMAND = 'play';

    /**
     * @var array<string, array{text: string, choices: list<array{id: string, label: string, next: string}>}>
     */
    private const array SCENES = [
        'gate' => [
            'text' => 'The voice channel shares one story. Two paths wait.',
            'choices' => [
                ['id' => 'left', 'label' => 'Take the left path', 'next' => 'left'],
                ['id' => 'right', 'label' => 'Take the right path', 'next' => 'right'],
            ],
        ],
        'left' => [
            'text' => 'The left path is quiet. A wooden marker stands in the grass.',
            'choices' => [
                ['id' => 'read', 'label' => 'Read the marker', 'next' => 'marker-end'],
                ['id' => 'back', 'label' => 'Go back', 'next' => 'gate'],
            ],
        ],
        'right' => [
            'text' => 'The right path ends at a shut gate.',
            'choices' => [
                ['id' => 'open', 'label' => 'Open the gate', 'next' => 'gate-end'],
                ['id' => 'back', 'label' => 'Go back', 'next' => 'gate'],
            ],
        ],
        'marker-end' => [
            'text' => 'The marker says: you walked this turn together.',
            'choices' => [
                ['id' => 'again', 'label' => 'Start again', 'next' => 'gate'],
            ],
        ],
        'gate-end' => [
            'text' => 'The gate opens. You walked this turn together.',
            'choices' => [
                ['id' => 'again', 'label' => 'Start again', 'next' => 'gate'],
            ],
        ],
    ];

    /**
     * @return array{name: string, description: string, type: int}
     */
    public function commandDefinition(): array
    {
        return [
            'name' => self::COMMAND,
            'description' => 'Start the LoreSpinner solo test',
            'type' => 1,
        ];
    }

    /**
     * @return array{type: int, data: array<string, mixed>}
     */
    public function opening(): array
    {
        return $this->message('gate', update: false);
    }

    /**
     * Scenes shared by the /play command and the Discord Activity.
     *
     * @return array<string, array{text: string, choices: list<array{id: string, label: string, next: string}>}>
     */
    public function scenes(): array
    {
        return self::SCENES;
    }

    /**
     * Move from a scene when the choice belongs to it.
     */
    public function advance(string $sceneId, string $choiceId): ?string
    {
        return $this->nextScene($sceneId, $choiceId);
    }

    /**
     * @return array{type: int, data: array<string, mixed>}
     */
    public function choose(string $customId): array
    {
        $parts = explode(':', $customId, 3);

        if (count($parts) !== 3 || $parts[0] !== 'solo') {
            return $this->ephemeral('That choice is not part of this test.');
        }

        $next = $this->nextScene($parts[1], $parts[2]);

        if ($next === null) {
            return $this->ephemeral('That choice is not part of this test.');
        }

        return $this->message($next, update: true);
    }

    private function nextScene(string $sceneId, string $choiceId): ?string
    {
        foreach (self::SCENES[$sceneId]['choices'] ?? [] as $choice) {
            if ($choice['id'] === $choiceId) {
                return $choice['next'];
            }
        }

        return null;
    }

    /**
     * @return array{type: int, data: array<string, mixed>}
     */
    private function message(string $sceneId, bool $update): array
    {
        $scene = self::SCENES[$sceneId];

        return [
            'type' => $update ? 7 : 4,
            'data' => [
                'content' => $scene['text'],
                'components' => [
                    [
                        'type' => 1,
                        'components' => array_map(
                            fn (array $choice): array => [
                                'type' => 2,
                                'style' => str_ends_with($sceneId, '-end') ? 2 : 1,
                                'label' => $choice['label'],
                                'custom_id' => "solo:{$sceneId}:{$choice['id']}",
                            ],
                            $scene['choices'],
                        ),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{type: int, data: array<string, mixed>}
     */
    private function ephemeral(string $content): array
    {
        return [
            'type' => 4,
            'data' => [
                'content' => $content,
                'flags' => 64,
            ],
        ];
    }
}
