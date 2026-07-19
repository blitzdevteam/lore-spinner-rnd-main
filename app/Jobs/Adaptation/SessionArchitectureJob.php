<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\SessionArchitectureValidator;
use App\Ai\Adaptation\VoiceProfilePromptSlice;
use App\Ai\Agents\Adaptation\SessionArchitectureAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Models\Story;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class SessionArchitectureJob implements ShouldQueue
{
    use Batchable, Queueable;

    private const int MAX_VALIDATION_ATTEMPTS = 2;

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
    public function handle(SessionArchitectureValidator $validator): void
    {
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
                ? $this->generateValidatedArchitecture($prompt, $validator, (array) $session->boiler_draft)
                : (new SessionArchitectureAgent(false))->prompt($prompt)->toArray();

            $session->update(['session_architecture' => $architecture]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function generateValidatedArchitecture(
        string $originalPrompt,
        SessionArchitectureValidator $validator,
        array $draft,
    ): array {
        $prompt = $originalPrompt;
        $lastValidationError = null;

        for ($attempt = 1; $attempt <= self::MAX_VALIDATION_ATTEMPTS; $attempt++) {
            $architecture = (new SessionArchitectureAgent(true))->prompt($prompt)->toArray();

            try {
                $validator->validate($architecture, $draft);

                if ($attempt > 1) {
                    Log::info('boiler.architecture_corrected', [
                        'story_id' => $this->story->id,
                        'session_number' => $this->sessionNumber,
                        'generation_attempt' => $attempt,
                    ]);
                }

                return $architecture;
            } catch (RuntimeException $validationError) {
                $lastValidationError = $validationError;
                Log::warning('boiler.architecture_validation_failed', [
                    'story_id' => $this->story->id,
                    'session_number' => $this->sessionNumber,
                    'generation_attempt' => $attempt,
                    'will_correct' => $attempt < self::MAX_VALIDATION_ATTEMPTS,
                    'error' => $validationError->getMessage(),
                ]);

                if ($attempt < self::MAX_VALIDATION_ATTEMPTS) {
                    $prompt = view('ai.agents.adaptation.session-architecture.repair-prompt', [
                        'validationError' => $validationError->getMessage(),
                        'previousArchitecture' => $architecture,
                        'originalPrompt' => $originalPrompt,
                    ])->render();
                }
            }
        }

        throw $lastValidationError
            ?? new RuntimeException('Session Architecture validation failed without an error.');
    }
}
