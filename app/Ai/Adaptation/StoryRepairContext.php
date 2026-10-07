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
     *     entry_decisions: array<string, mixed>,
     *     skip_targets: list<string>,
     *     previous_session: array<string, mixed>|null,
     *     next_session: array<string, mixed>|null,
     *     source_events: list<array{position: int, title: string, content: string}>
     * }
     */
    /**
     * @param  list<string>  $skipTargets
     */
    public function build(Story $story, SessionAdaptation $session, array $skipTargets = []): array
    {
        $adaptation = $story->adaptation;
        $sessions = $adaptation->sessionAdaptations()->orderBy('session_number')->get();
        [$previous, $next] = $this->neighbours($session);

        return [
            'story_title' => (string) $story->title,
            'story_slug' => (string) $story->slug,
            'session_number' => (int) $session->session_number,
            'total_sessions' => $sessions->count(),
            'protagonist' => $this->coldOpenContext->protagonistFor($story),
            'voice_profile' => (array) ($adaptation->voice_profile ?? []),
            'narrator_rules' => $this->narratorRules((string) $session->runtime_narrator_prompt),
            'universal_bans' => BannedProseValidator::universalBans(),
            'material' => array_diff_key($this->material($session), array_flip($skipTargets === [] ? [] : array_map(
                static fn (string $target): string => $target === 'scene_card' ? 'scene_cards' : $target,
                $skipTargets,
            ))),
            'entry_decisions' => $this->entryDecisions((array) ($session->entry_point_diagnosis ?? [])),
            'skip_targets' => $skipTargets,
            'previous_session' => $previous === null ? null : [
                'session_number' => (int) $previous->session_number,
                'session_close_design' => (array) ($previous->session_close_design ?? []),
            ],
            'next_session' => $next === null ? null : [
                'session_number' => (int) $next->session_number,
                'entry' => $this->entryMaterial($next),
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
        return [
            'scene_cards' => $this->moments($session)
                ->mapWithKeys(fn (PlayableMoment $moment): array => [$moment->moment_key => (array) $moment->scene_card])
                ->all(),
            'session_choice_design' => (array) ($session->session_choice_design ?? []),
            'choice_consequence_map' => (array) ($session->choice_consequence_map ?? []),
            'session_close_design' => (array) ($session->session_close_design ?? []),
            'entry_point_diagnosis' => $this->entryMaterial($session),
        ];
    }

    /**
     * The handoff material of the neighbouring sessions, keyed by patch target,
     * so a repair can fix both sides of a session boundary in one review.
     *
     * @return array{previous_session_close: array<string, mixed>, next_session_entry: array<string, mixed>}
     */
    public function neighbourMaterial(SessionAdaptation $session): array
    {
        [$previous, $next] = $this->neighbours($session);

        return [
            'previous_session_close' => $previous === null ? [] : (array) ($previous->session_close_design ?? []),
            'next_session_entry' => $next === null ? [] : $this->entryMaterial($next),
        ];
    }

    /**
     * @return array{0: ?SessionAdaptation, 1: ?SessionAdaptation}
     */
    public function neighbours(SessionAdaptation $session): array
    {
        $siblings = SessionAdaptation::query()
            ->where('story_adaptation_id', $session->story_adaptation_id)
            ->whereIn('session_number', [$session->session_number - 1, $session->session_number + 1])
            ->get()
            ->keyBy('session_number');

        return [$siblings->get($session->session_number - 1), $siblings->get($session->session_number + 1)];
    }

    /**
     * The entry-point fields the cold open is written from, all patchable: the
     * opening itself, what it establishes and holds back, the facts it must
     * say plainly, and the first choice it hands to.
     *
     * @return array<string, mixed>
     */
    public function entryMaterial(SessionAdaptation $session): array
    {
        return array_intersect_key((array) ($session->entry_point_diagnosis ?? []), array_flip([
            'cold_open', 'first_choice_spec', 'emotional_promise', 'situation_stakes_world', 'format_specific_cut',
        ]));
    }

    /**
     * @param  array<string, mixed>  $diagnosis
     * @return array<string, mixed>
     */
    public function entryDecisions(array $diagnosis): array
    {
        return array_filter([
            'start_event_position' => $diagnosis['start_event_position'] ?? null,
        ], static fn ($value): bool => $value !== null && $value !== '');
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
