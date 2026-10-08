<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\BannedProseValidator;
use App\Ai\Adaptation\EntryPointDiagnosisValidator;
use App\Ai\Adaptation\PlayableProtagonist;
use App\Ai\Adaptation\StorySessionMapSlice;
use App\Ai\Adaptation\VoiceProfilePromptSlice;
use App\Ai\Agents\Adaptation\EntryPointDiagnosisAgent;
use App\ChaosMode\ChaosStoryConfig;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Exceptions\Adaptation\ArtifactValidationException;
use App\Models\Event;
use App\Models\Story;
use App\Services\Adaptation\SessionEventLoader;
use App\Support\Adaptation\GeneratesValidatedArtifact;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

final class EntryPointDiagnosisJob implements ShouldQueue
{
    use Batchable, GeneratesValidatedArtifact, Queueable;

    private const int PREVIOUS_ENDING_EVENTS = 2;

    private const int PREVIOUS_ENDING_MAX_CHARS = 1500;

    public int $tries = 2;

    public int $timeout = 900;

    public int $backoff = 60;

    public function __construct(
        private Story $story,
        private int $sessionNumber,
    ) {
        $this->onQueue('adaptation');
    }

    /** @throws Throwable */
    public function handle(SessionEventLoader $eventLoader, EntryPointDiagnosisValidator $validator): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $adaptation = $this->story->adaptation;
        $session = $adaptation->sessionAdaptations()->where('session_number', $this->sessionNumber)->firstOrFail();

        if (empty($adaptation->voice_profile)) {
            throw new RuntimeException(
                'voice_profile missing — Voice Lock must complete before Phase 3 (EntryPointDiagnosis)'
            );
        }

        // V2.3: Phase 3 (D10) requires voice_anchor and anchor_card.
        // dnaBansAndAnchor() throws RuntimeException if either is absent — no silent fallback.
        $voiceProfile = VoiceProfilePromptSlice::dnaBansAndAnchor((array) ($adaptation->voice_profile ?? []));

        try {
            $session->update(['session_status' => SessionAdaptationStatusEnum::ENTRY_POINT_DIAGNOSIS]);

            $sessionEvents = $eventLoader->forSession($this->story, $this->sessionNumber);

            if ($sessionEvents->isEmpty()) {
                throw new RuntimeException(
                    "No events found for story {$this->story->id} session {$this->sessionNumber}"
                );
            }

            // Use ip_trimming chapter segments for the correct session window.
            // Falls back to raw source if ip_trimming is not yet available.
            $sessionSourcePages = $this->story->getSessionTrimmedText($this->sessionNumber);

            $storySessionMap = $adaptation->story_session_map;
            $protagonist = PlayableProtagonist::for($this->story);
            $mustBreathePositions = $this->mustBreathePositions((array) $storySessionMap, $sessionEvents);

            // Format label for D10 prompt slot.
            $format = match (mb_strtoupper((string) ($adaptation->voice_profile['profile_type'] ?? ''))) {
                'SCREENWRITER' => 'SCREENPLAY (via 1B v3)',
                'NOVELIST' => 'NOVEL (via 1A v2)',
                default => mb_strtoupper((string) ($adaptation->voice_profile['profile_type'] ?? 'UNKNOWN')),
            };

            $prompt = view('ai.agents.adaptation.entry-point-diagnosis.prompt', [
                'storySessionMap' => StorySessionMapSlice::forSession((array) $storySessionMap, $this->sessionNumber),
                'ipAudit' => $adaptation->ip_audit,
                'sessionNumber' => $this->sessionNumber,
                'sessionSourcePages' => $sessionSourcePages,
                'sessionEvents' => $sessionEvents->map(fn (Event $ev) => [
                    'story_position' => $ev->story_position,
                    'position' => $ev->position,
                    'chapter_position' => $ev->chapter_position,
                    'title' => $ev->title,
                    'objectives' => $ev->objectives,
                ])->all(),
                'voiceProfile' => $voiceProfile,
                'protagonist' => $protagonist,
                'format' => $format,
                'preferLiteralOpening' => (bool) (ChaosStoryConfig::find($this->story->slug)['prefer_literal_opening'] ?? false),
                'previousSessionEnding' => $this->previousSessionEnding($eventLoader),
                'mustBreatheEventPositions' => $mustBreathePositions,
            ])->render();

            $result = $this->generateValidatedArtifact(
                artifact: 'entry_point',
                originalPrompt: $prompt,
                generate: function (string $attemptPrompt): array {
                    return BannedProseValidator::neutralizeArtifact($this->completeArtifact((new EntryPointDiagnosisAgent)->prompt($attemptPrompt), 'entry point'));
                },
                validate: fn (array $diagnosis) => $validator->validate($diagnosis, $sessionEvents, $mustBreathePositions, $protagonist),
                repairPrompt: fn (string $error, array $rejected): string => $prompt
                    ."\n\n---\n\nYOUR PREVIOUS OUTPUT WAS REJECTED. Fix every problem below and return the complete corrected JSON.\n- "
                    .str_replace("\n", "\n- ", $error)
                    ."\n\nREJECTED COLD OPEN:\n".(string) ($rejected['cold_open'] ?? ''),
                logContext: ['story_id' => $this->story->id, 'session_number' => $this->sessionNumber],
            );

            $startEvent = $sessionEvents->firstWhere('story_position', (int) $result['start_event_position']);
            $result['start_event_id'] = $startEvent->id;
            $result['start_event_position'] = $startEvent->story_position;
            $result['start_event_chapter_position'] = $startEvent->chapter_position;
            $result['start_event_local_position'] = $startEvent->position;

            $session->update(['entry_point_diagnosis' => $result]);
        } catch (ArtifactValidationException $exception) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            $this->failTerminally($exception);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }

    /**
     * The previous session's last source events, so the opening continues from
     * where the player actually leaves off.
     *
     * @return list<array{story_position: int, title: string, content: string}>
     */
    private function previousSessionEnding(SessionEventLoader $eventLoader): array
    {
        if ($this->sessionNumber <= 1) {
            return [];
        }

        return $eventLoader->forSession($this->story, $this->sessionNumber - 1)
            ->take(-self::PREVIOUS_ENDING_EVENTS)
            ->map(fn (Event $event): array => [
                'story_position' => (int) $event->story_position,
                'title' => (string) $event->title,
                'content' => mb_substr(mb_trim((string) $event->content), -self::PREVIOUS_ENDING_MAX_CHARS),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $storySessionMap
     * @param  Collection<int, Event>  $sessionEvents
     * @return list<int>
     */
    private function mustBreathePositions(array $storySessionMap, Collection $sessionEvents): array
    {
        $guidance = collect((array) data_get($storySessionMap, 'boiling_profile.per_session_guidance', []))
            ->firstWhere('session_number', $this->sessionNumber);
        $ids = array_map('intval', (array) data_get($guidance, 'must_breathe_event_ids', []));

        return $sessionEvents
            ->filter(fn (Event $event): bool => in_array((int) $event->id, $ids, true))
            ->map(fn (Event $event): int => (int) $event->story_position)
            ->sort()
            ->values()
            ->all();
    }
}
