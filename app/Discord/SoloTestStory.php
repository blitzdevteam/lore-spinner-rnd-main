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
            'text' => 'Solo test. You are alone in a small room. A lantern hangs by one shut door.',
            'choices' => [
                ['id' => 'open', 'label' => 'Open the door', 'next' => 'hall'],
                ['id' => 'lantern', 'label' => 'Take the lantern', 'next' => 'lantern'],
            ],
        ],
        'hall' => [
            'text' => 'The door opens onto an empty hall. A bell sits on a table.',
            'choices' => [
                ['id' => 'ring', 'label' => 'Ring the bell', 'next' => 'bell-end'],
                ['id' => 'back', 'label' => 'Turn back', 'next' => 'gate'],
            ],
        ],
        'lantern' => [
            'text' => 'The lantern is warm. A note on the handle says: one step is enough.',
            'choices' => [
                ['id' => 'read', 'label' => 'Read the note', 'next' => 'note-end'],
                ['id' => 'back', 'label' => 'Put it back', 'next' => 'gate'],
            ],
        ],
        'bell-end' => [
            'text' => 'The bell rings once. This solo test is complete.',
            'choices' => [
                ['id' => 'again', 'label' => 'Play again', 'next' => 'gate'],
            ],
        ],
        'note-end' => [
            'text' => 'You finish the note. This solo test is complete.',
            'choices' => [
                ['id' => 'again', 'label' => 'Play again', 'next' => 'gate'],
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
