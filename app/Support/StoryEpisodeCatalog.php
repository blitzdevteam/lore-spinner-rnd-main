<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Models\Chapter;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameSessionCompletion;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\StoryAdaptation;
use Illuminate\Support\Collection;

/**
 * Player-facing episodes are adaptation sessions, not extracted book chapters.
 *
 * Chapters remain the authoring unit (script extraction, event ownership); a
 * session spans many of them, so listing chapters shows the wrong count and
 * cannot be reconciled with `games.current_session_number`.
 */
final class StoryEpisodeCatalog
{
    private const TEASER_LIMIT = 190;

    /**
     * @return list<array{
     *     number: int,
     *     teaser: string|null,
     *     cover: string,
     *     is_playable: bool,
     *     is_completed: bool,
     * }>
     */
    public static function for(Story $story, ?Game $game = null): array
    {
        $adaptation = $story->relationLoaded('adaptation')
            ? $story->adaptation
            : $story->adaptation()->first();

        if (! $adaptation instanceof StoryAdaptation) {
            return [];
        }

        $sessions = self::sessions($adaptation);

        if ($sessions->isEmpty()) {
            return [];
        }

        $fallbackTeasers = self::teasersBySession($adaptation);
        $covers = self::coversBySession($story);
        $completed = self::completedSessionNumbers($game);

        $episodes = [];

        foreach ($sessions as $session) {
            $number = (int) $session->session_number;
            $teaser = self::coldOpenHook($session) ?: ($fallbackTeasers[$number] ?? '');

            $episodes[] = [
                'number' => $number,
                'teaser' => $teaser === '' ? null : $teaser,
                'cover' => $covers[$number] ?? '',
                'is_playable' => self::isSessionPlayable($session, $adaptation),
                'is_completed' => in_array($number, $completed, true),
            ];
        }

        return $episodes;
    }

    /**
     * @return Collection<int, SessionAdaptation>
     */
    private static function sessions(StoryAdaptation $adaptation): Collection
    {
        $sessions = $adaptation->relationLoaded('sessionAdaptations')
            ? $adaptation->sessionAdaptations
            : $adaptation->sessionAdaptations()->get();

        return $sessions
            ->sortBy('session_number')
            ->values();
    }

    /**
     * The cold open is authored prose for the session's first minute, so its
     * opening paragraph hooks the player without spoiling later beats.
     */
    private static function coldOpenHook(SessionAdaptation $session): string
    {
        $coldOpen = data_get($session->entry_point_diagnosis, 'cold_open');

        if (! is_string($coldOpen)) {
            return '';
        }

        $paragraphs = preg_split('/\R{2,}/u', mb_trim($coldOpen)) ?: [];
        $opening = is_string($paragraphs[0] ?? null) ? $paragraphs[0] : '';

        return self::normalizeTeaser(str_replace(['*', '_'], '', $opening));
    }

    /**
     * Dramatic questions cover sessions whose cold open is not written yet; the
     * raw chapter teaser is script prose and is never used here.
     *
     * @return array<int, string>
     */
    private static function teasersBySession(StoryAdaptation $adaptation): array
    {
        $map = (array) ($adaptation->story_session_map ?? []);
        $teasers = [];

        foreach (['session_allocation', 'arc_progression'] as $section) {
            foreach ((array) ($map[$section] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $number = is_numeric($row['session_number'] ?? null) ? (int) $row['session_number'] : 0;

                if ($number <= 0 || isset($teasers[$number])) {
                    continue;
                }

                $hook = $row['primary_dramatic_question'] ?? $row['emotional_register'] ?? null;
                $teaser = self::normalizeTeaser(is_string($hook) ? $hook : '');

                if ($teaser !== '') {
                    $teasers[$number] = $teaser;
                }
            }
        }

        return $teasers;
    }

    private static function normalizeTeaser(string $raw): string
    {
        $teaser = mb_trim((string) preg_replace('/\s+/u', ' ', $raw));

        if ($teaser === '') {
            return '';
        }

        if (mb_strlen($teaser) <= self::TEASER_LIMIT) {
            return $teaser;
        }

        $truncated = mb_substr($teaser, 0, self::TEASER_LIMIT);
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace !== false && $lastSpace > 0) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return mb_rtrim($truncated, ' ,;:-').'…';
    }

    /**
     * Sessions inherit artwork from the first chapter their events cover.
     *
     * @return array<int, string>
     */
    private static function coversBySession(Story $story): array
    {
        $rows = Event::query()
            ->join('chapters', 'chapters.id', '=', 'events.chapter_id')
            ->where('chapters.story_id', $story->id)
            ->whereNotNull('events.session_number')
            ->orderBy('chapters.position')
            ->orderBy('events.position')
            ->get(['events.session_number', 'events.chapter_id']);

        if ($rows->isEmpty()) {
            return [];
        }

        $chapterIdBySession = [];

        foreach ($rows as $row) {
            $number = (int) $row->session_number;

            if (! isset($chapterIdBySession[$number])) {
                $chapterIdBySession[$number] = (int) $row->chapter_id;
            }
        }

        $chapters = Chapter::query()
            ->with('media')
            ->whereIn('id', array_values(array_unique($chapterIdBySession)))
            ->get(['id', 'story_id'])
            ->keyBy('id');

        $covers = [];

        foreach ($chapterIdBySession as $number => $chapterId) {
            $cover = $chapters->get($chapterId)?->getFirstMediaUrl('cover') ?? '';

            if ($cover !== '') {
                $covers[$number] = $cover;
            }
        }

        return $covers;
    }

    /**
     * A session is finished when its completion row is stamped for the current
     * story cycle, or when it is the live session and already resolved.
     *
     * @return list<int>
     */
    private static function completedSessionNumbers(?Game $game): array
    {
        if (! $game instanceof Game) {
            return [];
        }

        $numbers = GameSessionCompletion::query()
            ->where('game_id', $game->id)
            ->where('story_cycle_number', $game->current_story_cycle_number)
            ->whereNotNull('completed_at')
            ->get(['session_number'])
            ->map(fn (GameSessionCompletion $completion): int => $completion->session_number)
            ->all();

        if ($game->current_session_complete && $game->current_session_number !== null) {
            $numbers[] = (int) $game->current_session_number;
        }

        return array_values(array_unique($numbers));
    }

    private static function isSessionPlayable(SessionAdaptation $session, StoryAdaptation $adaptation): bool
    {
        if ($session->session_status !== SessionAdaptationStatusEnum::COMPLETED) {
            return false;
        }

        return ! $adaptation->boiler_enabled
            || $session->boiler_status === BoilerStatus::FINALIZED;
    }
}
