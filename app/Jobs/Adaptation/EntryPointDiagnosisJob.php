<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\VoiceProfilePromptSlice;
use App\Ai\Agents\Adaptation\EntryPointDiagnosisAgent;
use App\ChaosMode\ChaosStoryConfig;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Event;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Services\Adaptation\SessionEventLoader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Batchable;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

final class EntryPointDiagnosisJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;
    public int $timeout = 300;
    public int $backoff = 60;

    public function __construct(
        private Story $story,
        private int $sessionNumber,
    ) {
        $this->onQueue('adaptation');
    }

    /** @throws Throwable */
    public function handle(SessionEventLoader $eventLoader): void
    {
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
            $ipAudit         = $adaptation->ip_audit;

            // Derive protagonist name from session map or ip_audit (best-effort; Phase 3 D10 slot).
            $protagonist = $storySessionMap['protagonist'] ?? ($ipAudit['protagonist'] ?? $this->story->title);

            // Format label for D10 prompt slot.
            $format = match (strtoupper((string) ($adaptation->voice_profile['profile_type'] ?? ''))) {
                'SCREENWRITER' => 'SCREENPLAY (via 1B v3)',
                'NOVELIST'     => 'NOVEL (via 1A v2)',
                default        => strtoupper((string) ($adaptation->voice_profile['profile_type'] ?? 'UNKNOWN')),
            };

            $response = (new EntryPointDiagnosisAgent)->prompt(
                view('ai.agents.adaptation.entry-point-diagnosis.prompt', [
                    'storySessionMap'    => $storySessionMap,
                    'ipAudit'            => $ipAudit,
                    'sessionNumber'      => $this->sessionNumber,
                    'sessionSourcePages' => $sessionSourcePages,
                    'sessionEvents'      => $sessionEvents->map(fn (Event $ev) => [
                        'story_position'   => $ev->story_position,
                        'position'         => $ev->position,
                        'chapter_position' => $ev->chapter_position,
                        'title'            => $ev->title,
                        'objectives'       => $ev->objectives,
                    ])->all(),
                    'voiceProfile'          => $voiceProfile,
                    'protagonist'           => $protagonist,
                    'format'                => $format,
                    'preferLiteralOpening'  => (bool) (ChaosStoryConfig::find($this->story->slug)['prefer_literal_opening'] ?? false),
                ])->render()
            );

            $result = $response->toArray();

            $startPos = $result['start_event_position'] ?? null;
            $startEvent = $sessionEvents->firstWhere('story_position', $startPos)
                ?? $sessionEvents->first();

            $result['start_event_id'] = $startEvent->id;
            $result['start_event_position'] = $startEvent->story_position;
            $result['start_event_chapter_position'] = $startEvent->chapter_position;
            $result['start_event_local_position'] = $startEvent->position;

            $session->update(['entry_point_diagnosis' => $result]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }

}
