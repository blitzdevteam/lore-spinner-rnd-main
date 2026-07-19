<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\VoiceProfilePromptSlice;
use App\Ai\Agents\Adaptation\SessionArchitectureAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Batchable;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

final class SessionArchitectureJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;
    public int $timeout = 420;
    public int $backoff = 60;

    public function __construct(
        private Story $story,
        private int $sessionNumber,
    ) {
        $this->onQueue('adaptation');
    }

    /** @throws Throwable */
    public function handle(): void
    {
        $adaptation = $this->story->adaptation;
        $session = $adaptation->sessionAdaptations()->where('session_number', $this->sessionNumber)->firstOrFail();

        if (empty($adaptation->voice_profile)) {
            throw new \RuntimeException(
                'voice_profile missing — Voice Lock must complete before Phase 4 (SessionArchitecture)'
            );
        }

        try {
            $session->update(['session_status' => SessionAdaptationStatusEnum::SESSION_ARCHITECTURE]);

            // Use ip_trimming chapter segments for the correct session window.
            // Falls back to raw source if ip_trimming is not yet available.
            $sessionSourcePages = $this->story->getSessionTrimmedText($this->sessionNumber);

            $response = (new SessionArchitectureAgent)->prompt(
                view('ai.agents.adaptation.session-architecture.prompt', [
                    'storySessionMap' => $adaptation->story_session_map,
                    'entryPointDiagnosis' => $session->entry_point_diagnosis,
                    'sessionNumber' => $this->sessionNumber,
                    'sessionSourcePages' => $sessionSourcePages,
                    'voiceProfile' => VoiceProfilePromptSlice::dna((array) ($adaptation->voice_profile ?? [])),
                    'boilerDraft' => $adaptation->boiler_enabled ? $session->boiler_draft : null,
                ])->render()
            );

            $architecture = $response->toArray();

            if ($adaptation->boiler_enabled) {
                $this->validateBoilerBindings($architecture, (array) $session->boiler_draft);
            }

            $session->update(['session_architecture' => $architecture]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }

    /**
     * @param  array<string, mixed>  $architecture
     * @param  array<string, mixed>  $draft
     */
    private function validateBoilerBindings(array $architecture, array $draft): void
    {
        $momentKeys = collect((array) ($draft['moment_drafts'] ?? []))
            ->pluck('moment_key')
            ->filter()
            ->values()
            ->all();

        if ($momentKeys === []) {
            throw new RuntimeException('Boiler-enabled Session Architecture requires a validated Boiler Draft.');
        }

        $alignment = $architecture['boiler_budget_alignment'] ?? null;
        if (! is_array($alignment) || ($alignment['aligned'] ?? null) !== true) {
            $conflict = is_array($alignment) ? ($alignment['conflict'] ?? 'unspecified conflict') : 'missing alignment result';
            throw new RuntimeException("Session Architecture conflicts with the approved Boiler budget: {$conflict}");
        }

        foreach ((array) ($architecture['beat_map'] ?? []) as $beat) {
            if (! is_array($beat) || ! in_array($beat['moment_key'] ?? null, $momentKeys, true)) {
                throw new RuntimeException('Every Boiler-enabled beat-map row must reference a valid draft moment_key.');
            }
        }
    }
}
