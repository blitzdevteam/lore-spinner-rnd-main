<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use App\Models\Story;
use App\Services\Adaptation\SessionEventLoader;
use RuntimeException;

final class BoilerDraftContextBuilder
{
    private const int SESSION_SOURCE_CHAR_BUDGET = 60_000;

    private const int EVENT_SOURCE_CHAR_LIMIT = 8_000;

    public function __construct(private SessionEventLoader $eventLoader) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Story $story, int $sessionNumber): array
    {
        $adaptation = $story->adaptation;
        $session = $adaptation?->sessionAdaptations()
            ->where('session_number', $sessionNumber)
            ->firstOrFail();

        if (! $adaptation || ! $session) {
            throw new RuntimeException('Boiler Draft requires an existing story and session adaptation.');
        }

        $storyMap = (array) ($adaptation->story_session_map ?? []);
        $entryPoint = (array) ($session->entry_point_diagnosis ?? []);
        $startPosition = isset($entryPoint['start_event_position'])
            ? (int) $entryPoint['start_event_position']
            : null;
        $events = $this->eventLoader->forSession($story, $sessionNumber, $startPosition);

        if ($events->isEmpty()) {
            throw new RuntimeException("No assigned events found for Boiler Draft session {$sessionNumber}.");
        }

        $guidance = collect((array) ($storyMap['boiling_profile']['per_session_guidance'] ?? []))
            ->firstWhere('session_number', $sessionNumber);

        if (! is_array($guidance)) {
            throw new RuntimeException("Missing boiling profile guidance for session {$sessionNumber}.");
        }

        $remainingChars = self::SESSION_SOURCE_CHAR_BUDGET;
        $eventContext = $events->map(function ($event) use (&$remainingChars): array {
            $content = (string) $event->content;
            $extractLength = min(self::EVENT_SOURCE_CHAR_LIMIT, max(0, $remainingChars));
            $extract = $extractLength > 0 ? mb_substr($content, 0, $extractLength) : '';
            $remainingChars -= mb_strlen($extract);

            return [
                'event_id' => (int) $event->id,
                'story_position' => (int) $event->story_position,
                'chapter_id' => (int) $event->chapter_id,
                'chapter_position' => (int) $event->chapter_position,
                'chapter_local_position' => (int) $event->position,
                'title' => (string) $event->title,
                'objectives' => (string) ($event->objectives ?? ''),
                'source_extract' => $extract,
                'source_was_truncated' => mb_strlen($content) > mb_strlen($extract),
                'before_entry_point' => (bool) $event->before_entry_point,
            ];
        })->all();

        return [
            'story' => [
                'id' => $story->id,
                'title' => $story->title,
            ],
            'session_number' => $sessionNumber,
            'session_allocation' => collect((array) ($storyMap['session_allocation'] ?? []))
                ->firstWhere('session_number', $sessionNumber),
            'boiling_profile' => (array) ($storyMap['boiling_profile'] ?? []),
            'session_guidance' => $guidance,
            'entry_point_diagnosis' => $entryPoint,
            'format_detection' => (array) ($adaptation->format_detection ?? []),
            'player_role_and_agency_constraints' => [
                'bounded_agency' => data_get($adaptation->ip_audit, 'bounded_agency'),
                'canon' => (array) ($storyMap['story_guard_canon'] ?? []),
            ],
            'branch_opportunities' => collect((array) ($storyMap['branch_opportunities'] ?? []))
                ->where('session_number', $sessionNumber)
                ->values()
                ->all(),
            'trimming_notes' => (array) data_get($adaptation->ip_trimming, 'interactive_conversion_notes', []),
            'voice_profile' => VoiceProfilePromptSlice::dnaAndBans((array) ($adaptation->voice_profile ?? [])),
            'events' => $eventContext,
        ];
    }
}
