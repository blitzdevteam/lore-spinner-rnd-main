<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\SessionArchitectureCanonicalizer;
use App\Ai\Adaptation\SessionArchitectureValidator;
use App\Ai\Adaptation\VoiceProfilePromptSlice;
use App\Ai\Agents\Adaptation\SessionArchitectureAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Exceptions\Adaptation\ArtifactValidationException;
use App\Models\Story;
use App\Support\Adaptation\GeneratesValidatedArtifact;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

final class SessionArchitectureJob implements ShouldQueue
{
    use Batchable, GeneratesValidatedArtifact, Queueable;

    public int $tries = 2;

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
        if ($this->batch()?->cancelled()) {
            return;
        }

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

            $architecture = $adaptation->boiler_enabled
                ? $this->generateValidatedArchitecture(
                    $prompt,
                    $canonicalizer,
                    $validator,
                    (array) $session->boiler_draft,
                )
                : (new SessionArchitectureAgent(false))->prompt($prompt)->toArray();

            $session->update(['session_architecture' => $architecture]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);

            if ($throwable instanceof ArtifactValidationException) {
                $this->failTerminally($throwable);

                return;
            }

            throw $throwable;
        }
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function generateValidatedArchitecture(
        string $originalPrompt,
        SessionArchitectureCanonicalizer $canonicalizer,
        SessionArchitectureValidator $validator,
        array $draft,
    ): array {
        return $this->generateValidatedArtifact(
            artifact: 'architecture',
            originalPrompt: $originalPrompt,
            generate: fn (string $prompt): array => $canonicalizer->canonicalize(
                (new SessionArchitectureAgent(true))->prompt($prompt)->toArray(),
                $draft,
            ),
            validate: fn (array $architecture) => $validator->validate($architecture, $draft),
            repairPrompt: fn (string $error, array $previousArchitecture): string => view(
                'ai.agents.adaptation.session-architecture.repair-prompt',
                [
                    'validationError' => $error,
                    'previousArchitecture' => $previousArchitecture,
                    'originalPrompt' => $originalPrompt,
                ],
            )->render(),
            logContext: [
                'story_id' => $this->story->id,
                'session_number' => $this->sessionNumber,
            ],
        );
    }
}
