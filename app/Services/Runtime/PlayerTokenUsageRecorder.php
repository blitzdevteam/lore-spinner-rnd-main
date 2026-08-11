<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use App\Models\Event;
use App\Models\Game;
use App\Models\GameSessionCompletion;
use App\Models\PlayableMoment;
use App\Models\PlayerTokenUsage;
use App\Models\Prompt;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class PlayerTokenUsageRecorder
{
    public function __construct(private PlayerTokenUsageCalculator $calculator) {}

    /**
     * @param  array<string, mixed>  $rawUsage
     */
    public function record(
        Game $game,
        Prompt $prompt,
        array $rawUsage,
        string $kind,
        ?PlayableMoment $moment = null,
        ?CarbonInterface $occurredAt = null,
        ?string $systemPrompt = null,
    ): PlayerTokenUsage {
        if ($prompt->game_id !== $game->id) {
            throw new LogicException('Player token usage prompt does not belong to the supplied game.');
        }

        $at = $occurredAt === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::instance($occurredAt)->utc();
        $calculated = $this->calculator->calculate($game->model, $rawUsage, $at);
        $attribution = $this->resolveAttribution($game, $moment);

        return DB::transaction(function () use (
            $game,
            $prompt,
            $rawUsage,
            $kind,
            $moment,
            $at,
            $calculated,
            $attribution,
            $systemPrompt,
        ): PlayerTokenUsage {
            $sessionCompletionId = GameSessionCompletion::query()
                ->where('game_id', $game->id)
                ->where('story_cycle_number', $game->current_story_cycle_number)
                ->where('session_number', $prompt->session_number)
                ->value('id');

            $usage = PlayerTokenUsage::query()->firstOrCreate(
                ['prompt_reference_id' => $prompt->id],
                [
                    'game_id' => $game->id,
                    'prompt_id' => $prompt->id,
                    'source_prompt_reference_id' => $prompt->source_prompt_id,
                    'story_id' => $game->story_id,
                    'user_id' => $game->user_id,
                    'game_session_completion_id' => $sessionCompletionId,
                    'playable_moment_id' => $moment?->id,
                    'anchor_event_id' => $attribution['anchor_event_id'],
                    'anchor_chapter_id' => $attribution['anchor_chapter_id'],
                    'story_cycle_number' => $game->current_story_cycle_number,
                    'session_number' => (int) $prompt->session_number,
                    'moment_key' => $moment === null ? $prompt->moment_key : $moment->moment_key,
                    'source_event_ids' => $attribution['event_ids'],
                    'source_chapter_ids' => $attribution['chapter_ids'],
                    'kind' => $kind,
                    'system_prompt' => $systemPrompt,
                    'generated_output' => $prompt->response,
                    ...$calculated,
                    'raw_usage' => $rawUsage,
                    'occurred_at' => $at,
                ],
            );

            if ($usage->wasRecentlyCreated) {
                $usage->events()->sync($attribution['event_pivot']);
                $usage->chapters()->sync($attribution['chapter_pivot']);
            }

            return $usage->fresh(['events', 'chapters']) ?? $usage;
        });
    }

    /**
     * Moment-based calls are attributed to that moment's exact source events.
     * Legacy calls have no reliable event cursor, so they intentionally remain
     * scoped to the game and session instead of being assigned to every event in
     * the session. Chapter pivots are de-duplicated, which ensures a chapter
     * counts a call once even when several of its events fed the moment.
     *
     * @return array{
     *     anchor_event_id:?int,
     *     anchor_chapter_id:?int,
     *     event_ids:array<int, int>,
     *     chapter_ids:array<int, int>,
     *     event_pivot:array<int, array{attribution_role:string}>,
     *     chapter_pivot:array<int, array{attribution_role:string}>,
     * }
     */
    private function resolveAttribution(
        Game $game,
        ?PlayableMoment $moment,
    ): array {
        if ($moment === null) {
            return [
                'anchor_event_id' => null,
                'anchor_chapter_id' => null,
                'event_ids' => [],
                'chapter_ids' => [],
                'event_pivot' => [],
                'chapter_pivot' => [],
            ];
        }

        $requestedEventIds = [];
        foreach ($moment->source_event_ids as $eventId) {
            if ($eventId > 0) {
                $requestedEventIds[] = $eventId;
            }
        }
        if ($moment->anchor_event_id !== null && $moment->anchor_event_id > 0) {
            $requestedEventIds[] = $moment->anchor_event_id;
        }
        $requestedEventIds = array_values(array_unique($requestedEventIds));

        /** @var Collection<int, Event> $events */
        $events = Event::query()
            ->join('chapters', 'chapters.id', '=', 'events.chapter_id')
            ->where('chapters.story_id', $game->story_id)
            ->whereIn('events.id', $requestedEventIds)
            ->orderBy('chapters.position')
            ->orderBy('events.position')
            ->get(['events.id', 'events.chapter_id']);

        $eventIds = $events
            ->map(fn (Event $event): int => $event->id)
            ->values()
            ->all();
        $chapterIds = $events
            ->map(fn (Event $event): int => $event->chapter_id)
            ->unique()
            ->values()
            ->all();
        $anchorEvent = $this->anchorEvent($events, $moment);
        $anchorChapterId = $anchorEvent === null ? null : (int) $anchorEvent->chapter_id;

        $eventPivot = [];
        foreach ($eventIds as $eventId) {
            $eventPivot[$eventId] = [
                'attribution_role' => $eventId === $anchorEvent?->id ? 'anchor' : 'moment_source',
            ];
        }

        $chapterPivot = [];
        foreach ($chapterIds as $chapterId) {
            $chapterPivot[$chapterId] = [
                'attribution_role' => $chapterId === $anchorChapterId ? 'anchor' : 'moment_source',
            ];
        }

        return [
            'anchor_event_id' => $anchorEvent?->id,
            'anchor_chapter_id' => $anchorChapterId,
            'event_ids' => $eventIds,
            'chapter_ids' => $chapterIds,
            'event_pivot' => $eventPivot,
            'chapter_pivot' => $chapterPivot,
        ];
    }

    /**
     * @param  Collection<int, Event>  $events
     */
    private function anchorEvent(Collection $events, ?PlayableMoment $moment): ?Event
    {
        if ($moment?->anchor_event_id === null) {
            return $events->count() === 1 ? $events->first() : null;
        }

        return $events->first(
            fn (Event $event): bool => $event->id === (int) $moment->anchor_event_id,
        );
    }
}
