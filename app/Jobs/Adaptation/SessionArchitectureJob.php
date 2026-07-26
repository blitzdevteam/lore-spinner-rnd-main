<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\SessionArchitectureCanonicalizer;
use App\Ai\Adaptation\SessionArchitectureValidator;
use App\Ai\Adaptation\VoiceProfilePromptSlice;
use App\Ai\Agents\Adaptation\SessionArchitectureAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Models\Story;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

final class SessionArchitectureJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public int $backoff = 60;

    public function __construct(
        private Story $story,
        private int $sessionNumber,
    ) {
        $this->onQueue('adaptation');
    }

    /** @throws Throwable */
    public function handle(
        SessionArchitectureCanonicalizer $canonicalizer,
        SessionArchitectureValidator $validator,
    ): void {
        $adaptation = $this->story->adaptation;
        $session = $adaptation->sessionAdaptations()->where('session_number', $this->sessionNumber)->firstOrFail();

        if (empty($adaptation->voice_profile)) {
            throw new RuntimeException(
                'voice_profile missing — Voice Lock must complete before Phase 4 (SessionArchitecture)'
            );
        }

        try {
            if (
                $adaptation->boiler_enabled
                && (
                    $session->boiler_status !== BoilerStatus::DRAFTED
                    || empty($session->boiler_draft)
                )
            ) {
                throw new RuntimeException('Boiler-enabled Session Architecture requires a validated Boiler Draft.');
            }

            $session->update(['session_status' => SessionAdaptationStatusEnum::SESSION_ARCHITECTURE]);

            // Use ip_trimming chapter segments for the correct session window.
            // Falls back to raw source if ip_trimming is not yet available.
            $sessionSourcePages = $this->story->getSessionTrimmedText($this->sessionNumber);

            $prompt = view('ai.agents.adaptation.session-architecture.prompt', [
                'storySessionMap' => $adaptation->story_session_map,
                'entryPointDiagnosis' => $session->entry_point_diagnosis,
                'sessionNumber' => $this->sessionNumber,
                'sessionSourcePages' => $sessionSourcePages,
                'voiceProfile' => VoiceProfilePromptSlice::dna((array) ($adaptation->voice_profile ?? [])),
                'boilerDraft' => $adaptation->boiler_enabled ? $session->boiler_draft : null,
            ])->render();

            $architecture = (new SessionArchitectureAgent((bool) $adaptation->boiler_enabled))
                ->prompt($prompt)
                ->toArray();

            if ($adaptation->boiler_enabled) {
                $architecture = $canonicalizer->canonicalize($architecture, (array) $session->boiler_draft);
                $validator->validate($architecture, (array) $session->boiler_draft);
            }

            $session->update(['session_architecture' => $architecture]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }
}
