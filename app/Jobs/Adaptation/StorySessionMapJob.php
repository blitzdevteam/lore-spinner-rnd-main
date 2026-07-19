<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\VoiceProfilePromptSlice;
use App\Ai\Agents\Adaptation\StorySessionMapAgent;
use App\ChaosMode\ChaosStoryConfig;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Event;
use App\Models\Story;
use App\Services\Adaptation\SessionEventLoader;
use App\Support\Adaptation\SessionAdaptationChain;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class StorySessionMapJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public int $backoff = 60;

    public function __construct(
        private Story $story,
    ) {
        $this->onQueue('adaptation');
    }

    /**
     * @throws Throwable
     */
    public function handle(SessionEventLoader $eventLoader): void
    {
        $adaptation = $this->story->adaptation;

        if (empty($adaptation->voice_profile)) {
            throw new \RuntimeException(
                'voice_profile missing — Voice Lock must complete before Phase 2 (StorySessionMap)'
            );
        }

        try {
            $adaptation->update([
                'adaptation_status' => AdaptationStatusEnum::STORY_SESSION_MAP,
            ]);

            $formatDetection = $adaptation->format_detection;
            $ipAudit = $adaptation->ip_audit;

            $chapters = $this->story->chapters()
                ->orderBy('position')
                ->get(['id', 'position', 'title', 'teaser'])
                ->map(fn ($ch) => [
                    'position' => $ch->position,
                    'title' => $ch->title,
                ])
                ->all();

            $storyGlobalEvents = $eventLoader->forStory($this->story);
            $totalEvents = $storyGlobalEvents->count();

            $events = $storyGlobalEvents
                ->map(fn ($ev) => [
                    'id' => (int) $ev->id,
                    'story_position' => $ev->story_position,
                    'position' => $ev->position,
                    'title' => $ev->title,
                    'objectives' => $ev->objectives,
                    'chapter_position' => $ev->chapter_position,
                ])
                ->all();

            // V2: pass ip_trimming.world_rules and interactive_conversion_notes
            // so the session map agent can feed world rules into StoryGuard Canon
            // Extraction and use conversion notes for session content allocation.
            $ipTrimming = $adaptation->ip_trimming ?? [];

            $response = (new StorySessionMapAgent((bool) $adaptation->boiler_enabled))->prompt(
                view('ai.agents.adaptation.story-session-map.prompt', [
                    'ipAudit' => $ipAudit,
                    'formatDetection' => json_encode($formatDetection, JSON_PRETTY_PRINT),
                    'estimatedSessionCount' => $formatDetection['estimated_session_count'] ?? 1,
                    'chapters' => $chapters,
                    'events' => $events,
                    'totalEvents' => $totalEvents,
                    'ipTrimmingWorldRules' => $ipTrimming['world_rules'] ?? null,
                    'ipTrimmingConversionNotes' => $ipTrimming['interactive_conversion_notes'] ?? null,
                    'voiceProfile' => VoiceProfilePromptSlice::alignmentContext((array) ($adaptation->voice_profile ?? [])),
                    'preferLiteralOpening' => (bool) (ChaosStoryConfig::find($this->story->slug)['prefer_literal_opening'] ?? false),
                    'boilerEnabled' => (bool) $adaptation->boiler_enabled,
                ])->render()
            );

            $sessionMap = $response->toArray();

            if ($adaptation->boiler_enabled) {
                $this->validateBoilingProfile($sessionMap, $storyGlobalEvents);
            }

            DB::transaction(function () use ($sessionMap, $adaptation, $storyGlobalEvents): void {
                $adaptation->sessionAdaptations()->delete();
                $this->story->events()->update(['session_number' => null]);

                $adaptation->update([
                    'story_session_map' => $sessionMap,
                    'adaptation_status' => AdaptationStatusEnum::ADAPTING_SESSIONS,
                ]);

                $sessionNumbers = collect($sessionMap['session_allocation'])
                    ->pluck('session_number')
                    ->sort()
                    ->values();

                foreach ($sessionMap['session_allocation'] as $session) {
                    $range = $this->parseEventRange($session['event_range']);
                    $eventIds = $storyGlobalEvents
                        ->filter(fn ($ev) => $ev->story_position >= $range[0] && $ev->story_position <= $range[1])
                        ->pluck('id');

                    if ($eventIds->isEmpty()) {
                        throw new RuntimeException(sprintf(
                            'Session %d resolved to zero events for range "%s" (story has %d events, story-global positions 1..%d).',
                            $session['session_number'],
                            $session['event_range'],
                            $storyGlobalEvents->count(),
                            $storyGlobalEvents->count(),
                        ));
                    }

                    Event::whereIn('id', $eventIds)->update([
                        'session_number' => $session['session_number'],
                    ]);
                }

                foreach ($sessionNumbers as $num) {
                    $adaptation->sessionAdaptations()->create([
                        'session_number' => $num,
                        'session_status' => SessionAdaptationStatusEnum::PENDING,
                    ]);
                }
            });

            $sessionAdaptations = $adaptation->sessionAdaptations()->orderBy('session_number')->get();
            $storyId = $this->story->id;

            SessionAdaptationChain::dispatchBatch(
                $this->story,
                $sessionAdaptations->pluck('session_number')->all(),
                function () use ($storyId): void {
                    $story = Story::findOrFail($storyId);
                    AdaptationStatusReconciliationJob::dispatch($story)->onQueue('adaptation');
                },
            );
        } catch (Throwable $throwable) {
            $adaptation->update([
                'adaptation_status' => AdaptationStatusEnum::FAILED,
            ]);

            throw $throwable;
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parseEventRange(string $range): array
    {
        $parts = explode('-', $range);

        return [(int) trim($parts[0]), (int) trim($parts[1] ?? $parts[0])];
    }

    /**
     * @param  array<string, mixed>  $sessionMap
     * @param  Collection<int, Event>  $storyGlobalEvents
     */
    private function validateBoilingProfile(array $sessionMap, Collection $storyGlobalEvents): void
    {
        $profile = $sessionMap['boiling_profile'] ?? null;
        $allocations = $sessionMap['session_allocation'] ?? null;

        if (! is_array($profile) || ! is_array($allocations)) {
            throw new RuntimeException('Boiler-enabled Story Session Map requires boiling_profile and session_allocation.');
        }

        if (! in_array($profile['compaction_level'] ?? null, ['light', 'balanced', 'strong'], true)) {
            throw new RuntimeException('boiling_profile.compaction_level must be light, balanced, or strong.');
        }

        $targetTotal = $profile['target_total_playtime_minutes'] ?? null;
        if (! is_int($targetTotal) || $targetTotal < 1) {
            throw new RuntimeException('boiling_profile.target_total_playtime_minutes must be a positive integer.');
        }

        $guidance = $profile['per_session_guidance'] ?? null;
        if (! is_array($guidance) || count($guidance) !== count($allocations)) {
            throw new RuntimeException('boiling_profile must contain exactly one guidance entry per allocated session.');
        }

        $guidanceBySession = [];
        foreach ($guidance as $entry) {
            if (! is_array($entry) || ! is_int($entry['session_number'] ?? null)) {
                throw new RuntimeException('Each boiling_profile guidance entry requires an integer session_number.');
            }

            $sessionNumber = $entry['session_number'];
            if (isset($guidanceBySession[$sessionNumber])) {
                throw new RuntimeException("Duplicate boiling guidance for session {$sessionNumber}.");
            }
            $guidanceBySession[$sessionNumber] = $entry;
        }

        $targetMinutesSum = 0;
        foreach ($allocations as $allocation) {
            $sessionNumber = (int) ($allocation['session_number'] ?? 0);
            $entry = $guidanceBySession[$sessionNumber] ?? null;

            if (! is_array($entry)) {
                throw new RuntimeException("Missing boiling guidance for allocated session {$sessionNumber}.");
            }

            foreach (['target_playtime_minutes', 'recommended_moment_count_min', 'recommended_moment_count_max'] as $field) {
                if (! is_int($entry[$field] ?? null) || $entry[$field] < 1) {
                    throw new RuntimeException("boiling_profile session {$sessionNumber} {$field} must be a positive integer.");
                }
            }

            if ($entry['recommended_moment_count_min'] > $entry['recommended_moment_count_max']) {
                throw new RuntimeException("boiling_profile session {$sessionNumber} moment-count minimum exceeds maximum.");
            }

            $range = $this->parseEventRange((string) ($allocation['event_range'] ?? ''));
            $validEventIds = $storyGlobalEvents
                ->filter(fn (Event $event): bool => $event->story_position >= $range[0] && $event->story_position <= $range[1])
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            foreach (['must_breathe_event_ids', 'must_compress_event_ids'] as $field) {
                $ids = $entry[$field] ?? null;
                if (! is_array($ids)) {
                    throw new RuntimeException("boiling_profile session {$sessionNumber} {$field} must be an array.");
                }

                foreach ($ids as $eventId) {
                    if (! is_int($eventId) || ! in_array($eventId, $validEventIds, true)) {
                        throw new RuntimeException("boiling_profile session {$sessionNumber} {$field} contains an out-of-session event ID.");
                    }
                }
            }

            $targetMinutesSum += $entry['target_playtime_minutes'];
        }

        if (abs($targetMinutesSum - $targetTotal) > count($allocations)) {
            throw new RuntimeException('boiling_profile per-session minutes do not reconcile with target_total_playtime_minutes.');
        }
    }

}
