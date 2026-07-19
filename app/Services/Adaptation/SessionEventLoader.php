<?php

declare(strict_types=1);

namespace App\Services\Adaptation;

use App\Models\Event;
use App\Models\Story;
use Illuminate\Support\Collection;

final class SessionEventLoader
{
    /**
     * @return Collection<int, Event>
     */
    public function forSession(Story $story, int $sessionNumber, ?int $startEventPosition = null): Collection
    {
        return $this->forStory($story)
            ->where('session_number', $sessionNumber)
            ->values()
            ->map(function (Event $event) use ($startEventPosition): Event {
                $event->setAttribute(
                    'before_entry_point',
                    $startEventPosition !== null
                        && (int) $event->getAttribute('story_position') < $startEventPosition,
                );

                return $event;
            });
    }

    /**
     * @return Collection<int, Event>
     */
    public function forStory(Story $story): Collection
    {
        return Event::query()
            ->join('chapters', 'chapters.id', '=', 'events.chapter_id')
            ->where('chapters.story_id', $story->id)
            ->orderBy('chapters.position')
            ->orderBy('events.position')
            ->get([
                'events.id',
                'events.chapter_id',
                'events.position',
                'events.title',
                'events.content',
                'events.objectives',
                'events.session_number',
                'chapters.position as chapter_position',
                'chapters.title as chapter_title',
            ])
            ->values()
            ->map(function (Event $event, int $index): Event {
                $event->setAttribute('story_position', $index + 1);

                return $event;
            });
    }
}
