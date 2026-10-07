<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use App\Models\Story;

/**
 * Hydrates the cold-open repair prompt from a story's stored diagnosis,
 * voice anchor, and the source events around the chosen entry.
 *
 * This is the review path. It does not change the entry-point pipeline agent.
 */
final class ColdOpenRepairContext
{
    public const string CONTRACT_VERSION = '2026-10-06';

    /**
     * @param  array<string, mixed>  $diagnosis
     * @param  array<string, mixed>  $voiceProfile
     * @param  list<array{position: int, title: string, content: string}>  $sourceEvents
     * @param  array<string, string>  $previousSessionClose
     * @return array{
     *     story_title: string,
     *     story_slug: string,
     *     session_number: int,
     *     protagonist: string,
     *     voice_anchor: mixed,
     *     anchor_card: mixed,
     *     current_entry_point_diagnosis: array<string, mixed>,
     *     established: string,
     *     deferred: string,
     *     cold_open_facts: list<string>,
     *     first_choice: array{setup_prose: string, threshold_stake: string, question: string, options: list<string>},
     *     current_cold_open: string,
     *     source_events: list<array{position: int, title: string, content: string}>,
     *     previous_session_close: array<string, string>
     * }
     */
    public function build(
        string $title,
        string $slug,
        int $sessionNumber,
        string $protagonist,
        array $voiceProfile,
        array $diagnosis,
        array $sourceEvents,
        array $previousSessionClose = [],
    ): array {
        $firstChoice = (array) ($diagnosis['first_choice_spec'] ?? []);

        return [
            'story_title' => $title,
            'story_slug' => $slug,
            'session_number' => $sessionNumber,
            'protagonist' => $protagonist,
            'voice_anchor' => $voiceProfile['voice_anchor'] ?? [],
            'anchor_card' => $voiceProfile['anchor_card'] ?? [],
            'current_entry_point_diagnosis' => $diagnosis,
            'established' => (string) data_get($diagnosis, 'situation_stakes_world.established', ''),
            'deferred' => (string) data_get($diagnosis, 'situation_stakes_world.deferred', ''),
            'cold_open_facts' => $this->coldOpenFacts($diagnosis['format_specific_cut']['must_reintroduce'] ?? null),
            'first_choice' => [
                'setup_prose' => (string) ($firstChoice['setup_prose'] ?? ''),
                'threshold_stake' => (string) ($firstChoice['threshold_stake'] ?? ''),
                'question' => (string) ($firstChoice['question'] ?? ''),
                'options' => array_values(array_filter([
                    (string) ($firstChoice['option_1'] ?? ''),
                    (string) ($firstChoice['option_2'] ?? ''),
                    (string) ($firstChoice['option_3_unexpected'] ?? ''),
                ], fn (string $option): bool => $option !== '')),
            ],
            'current_cold_open' => (string) ($diagnosis['cold_open'] ?? ''),
            'source_events' => $sourceEvents,
            'previous_session_close' => array_filter($previousSessionClose, fn (string $text): bool => mb_trim($text) !== ''),
        ];
    }

    /**
     * How the previous session is authored to end, so a later session's cold
     * open starts where that close leaves the protagonist.
     *
     * @param  array<string, mixed>|null  $closeDesign
     * @return array<string, string>
     */
    public function previousSessionClose(?array $closeDesign, string $opensWith = ''): array
    {
        return [
            'resolution_prose' => (string) data_get($closeDesign, 'resolution_prose', ''),
            'hook_transition' => (string) data_get($closeDesign, 'hook_transition', ''),
            'final_line' => (string) data_get($closeDesign, 'session_end_choice.final_line', ''),
            'next_session_opens_with' => $opensWith,
        ];
    }

    public function protagonistFor(Story $story): string
    {
        return PlayableProtagonist::for($story);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function missingContext(array $context): ?string
    {
        if (mb_trim((string) $context['protagonist']) === '' || $context['protagonist'] === 'the protagonist') {
            return 'Protagonist name is missing, so the repair has nothing to address the player as.';
        }

        if ($context['voice_anchor'] === [] && $context['anchor_card'] === []) {
            return 'Voice anchor is missing. Re-run Voice Lock before repairing this cold open.';
        }

        $hasSituation = $context['established'] !== ''
            || $context['current_cold_open'] !== ''
            || $context['cold_open_facts'] !== []
            || $context['first_choice']['setup_prose'] !== '';

        if (! $hasSituation) {
            return 'Entry-point diagnosis has no situation, cold open, or first choice to repair from.';
        }

        return null;
    }

    /**
     * Events from the diagnosed start through the next few beats.
     * Positions are the story-global ordinals, not chapter-local ones.
     *
     * @param  list<array{position: int, title: string, content: string}>  $events
     * @return list<array{position: int, title: string, content: string}>
     */
    public function sourceWindow(array $events, int $startPosition): array
    {
        $window = [];
        $characters = 0;

        foreach ($events as $event) {
            $position = (int) $event['position'];

            if ($startPosition > 0 && ($position < $startPosition || $position > $startPosition + 11)) {
                continue;
            }

            if ($startPosition < 1 && count($window) >= 8) {
                break;
            }

            $content = mb_substr(mb_trim($event['content']), 0, 1500);
            $characters += mb_strlen($content);

            if ($window !== [] && $characters > 8000) {
                break;
            }

            $window[] = [
                'position' => $position,
                'title' => $event['title'],
                'content' => $content,
            ];
        }

        return $window;
    }

    /**
     * @return list<string>
     */
    private function coldOpenFacts(mixed $mustReintroduce): array
    {
        if (is_string($mustReintroduce)) {
            $fact = $this->plainFact($mustReintroduce);

            return $fact === '' ? [] : [$fact];
        }

        if (! is_array($mustReintroduce)) {
            return [];
        }

        $facts = [];

        foreach ($mustReintroduce as $item) {
            if (is_string($item)) {
                $fact = $this->plainFact($item);

                if ($fact !== '') {
                    $facts[] = $fact;
                }

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $marker = (string) ($item['marker'] ?? '');

            if ($marker !== '' && $marker !== 'COLD_OPEN') {
                continue;
            }

            $fact = $this->plainFact((string) ($item['fact'] ?? ''));

            if ($fact !== '') {
                $facts[] = $fact;
            }
        }

        return $facts;
    }

    /**
     * Diagnosis facts often end with an editor's reason ("this matters because...").
     * That reason is not a line for the player.
     */
    private function plainFact(string $fact): string
    {
        $plain = preg_replace('/\s*[;,]?\s*(?:this|which|that)\s+matters\s+because\b.*$/isu', '', $fact);

        return mb_trim((string) $plain, " \t\n\r\0\x0B;,");
    }
}
