<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use App\Models\PlayableMoment;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Services\Adaptation\SessionEventLoader;

/**
 * Gathers everything the story repair review needs for one session: the
 * stored material it may patch, the neighbouring sessions it must stay
 * continuous with, the source it must stay faithful to, and the same voice
 * and narration rules the live narrator is held to.
 */
final class StoryRepairContext
{
    private const SOURCE_EVENT_MAX_CHARS = 3000;

    private const SOURCE_TOTAL_MAX_CHARS = 90000;

    /** Runtime prompt sections that carry the narrator's voice and prose rules. */
    private const NARRATOR_RULE_SECTIONS = ['4', '14', '18'];

    public function __construct(
        private readonly SessionEventLoader $eventLoader,
        private readonly ColdOpenRepairContext $coldOpenContext,
    ) {}

    /**
     * @return array{
     *     story_title: string,
     *     story_slug: string,
     *     session_number: int,
     *     total_sessions: int,
     *     protagonist: string,
     *     voice_profile: array<string, mixed>,
     *     narrator_rules: string,
     *     universal_bans: array{punctuation: list<string>, words: list<string>, phrases: list<string>},
     *     material: array<string, mixed>,
     *     previous_session: array<string, mixed>|null,
     *     next_session: array<string, mixed>|null,
     *     source_events: list<array{position: int, title: string, content: string}>
     * }
     */
    public function build(Story $story, SessionAdaptation $session): array
    {
        $adaptation = $story->adaptation;
        $sessions = $adaptation->sessionAdaptations()->orderBy('session_number')->get();
        $previous = $sessions->firstWhere('session_number', $session->session_number - 1);
        $next = $sessions->firstWhere('session_number', $session->session_number + 1);

        return [
            'story_title' => (string) $story->title,
            'story_slug' => (string) $story->slug,
            'session_number' => (int) $session->session_number,
            'total_sessions' => $sessions->count(),
            'protagonist' => $this->coldOpenContext->protagonistFor($story),
            'voice_profile' => (array) ($adaptation->voice_profile ?? []),
            'narrator_rules' => $this->narratorRules((string) $session->runtime_narrator_prompt),
            'universal_bans' => BannedProseValidator::universalBans(),
            'material' => $this->material($session),
            'previous_session' => $previous === null ? null : [
                'session_number' => (int) $previous->session_number,
                'session_close_design' => $previous->session_close_design,
            ],
            'next_session' => $next === null ? null : [
                'session_number' => (int) $next->session_number,
                'cold_open' => (string) data_get($next->entry_point_diagnosis, 'cold_open', ''),
            ],
            'source_events' => $this->sourceEvents($story, (int) $session->session_number),
        ];
    }

    /**
     * The stored material the repair may patch, keyed by patch target.
     *
     * @return array<string, mixed>
     */
    public function material(SessionAdaptation $session): array
    {
        $diagnosis = (array) ($session->entry_point_diagnosis ?? []);

        return [
            'scene_cards' => $this->moments($session)
                ->mapWithKeys(fn (PlayableMoment $moment): array => [$moment->moment_key => (array) $moment->scene_card])
                ->all(),
            'session_choice_design' => (array) ($session->session_choice_design ?? []),
            'choice_consequence_map' => (array) ($session->choice_consequence_map ?? []),
            'session_close_design' => (array) ($session->session_close_design ?? []),
            'entry_point_diagnosis' => array_intersect_key($diagnosis, array_flip(['cold_open', 'first_choice_spec', 'emotional_promise'])),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, PlayableMoment>
     */
    public function moments(SessionAdaptation $session): \Illuminate\Support\Collection
    {
        $cut = $session->playableCut()
            ->where('status', 'finalized')
            ->where('cut_key', 'standard')
            ->first();

        return $cut === null ? collect() : $cut->moments()->orderBy('moment_order')->get();
    }

    public function narratorRules(string $runtimePrompt): string
    {
        $sections = [];

        foreach (self::NARRATOR_RULE_SECTIONS as $number) {
            $pattern = '/^### SECTION '.preg_quote($number, '/').':.*?(?=^### SECTION |\z)/ms';

            if (preg_match($pattern, $runtimePrompt, $matches) === 1) {
                $sections[] = mb_trim($matches[0]);
            }
        }

        return implode("\n\n", $sections);
    }

    /**
     * @return list<array{position: int, title: string, content: string}>
     */
    private function sourceEvents(Story $story, int $sessionNumber): array
    {
        $events = [];
        $total = 0;

        foreach ($this->eventLoader->forSession($story, $sessionNumber) as $event) {
            $content = mb_substr(mb_trim((string) $event->content), 0, self::SOURCE_EVENT_MAX_CHARS);
            $total += mb_strlen($content);

            if ($events !== [] && $total > self::SOURCE_TOTAL_MAX_CHARS) {
                break;
            }

            $events[] = [
                'position' => (int) $event->getAttribute('story_position'),
                'title' => (string) $event->title,
                'content' => $content,
            ];
        }

        return $events;
    }
}
