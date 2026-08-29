<?php

declare(strict_types=1);

namespace App\Support\Adaptation;

use App\Exceptions\Adaptation\ArtifactValidationException;
use Closure;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The generate -> canonicalize -> validate -> repair loop shared by every
 * artifact-producing adaptation phase.
 *
 * A failed validation is fed back to the agent as a repair prompt naming the exact
 * offending paths. When the attempts run out the loop raises ArtifactValidationException
 * so the calling job can fail terminally: a queue-level retry would replay the same
 * generations against the same inputs and learn nothing.
 *
 * Two attempts proved too tight in practice. A draft can trip several independent
 * voice bans at once, and a single repair pass often clears some while the model
 * reaches for another banned near-synonym. The third attempt is cheap relative to
 * losing the session and re-running the phase by hand.
 */
trait GeneratesValidatedArtifact
{
    private const int MAX_VALIDATION_ATTEMPTS = 3;

    /**
     * @param  string  $artifact  Log-event stem, e.g. "draft" for boiler.draft_validation_failed.
     * @param  Closure(string): array<string, mixed>  $generate  Prompt in, canonicalized artifact out.
     * @param  Closure(array<string, mixed>): void  $validate  Throws RuntimeException when invalid.
     * @param  Closure(string, array<string, mixed>): string  $repairPrompt  Error and rejected artifact in, corrective prompt out.
     * @param  array<string, mixed>  $logContext
     * @return array<string, mixed>
     *
     * @throws ArtifactValidationException
     */
    private function generateValidatedArtifact(
        string $artifact,
        string $originalPrompt,
        Closure $generate,
        Closure $validate,
        Closure $repairPrompt,
        array $logContext = [],
    ): array {
        $prompt = $originalPrompt;
        $lastValidationError = null;

        for ($attempt = 1; $attempt <= self::MAX_VALIDATION_ATTEMPTS; $attempt++) {
            $result = $generate($prompt);

            try {
                $validate($result);

                if ($attempt > 1) {
                    Log::info("boiler.{$artifact}_corrected", [
                        ...$logContext,
                        'generation_attempt' => $attempt,
                    ]);
                }

                return $result;
            } catch (RuntimeException $validationError) {
                $lastValidationError = $validationError;
                $willCorrect = $attempt < self::MAX_VALIDATION_ATTEMPTS;

                Log::warning("boiler.{$artifact}_validation_failed", [
                    ...$logContext,
                    'generation_attempt' => $attempt,
                    'will_correct' => $willCorrect,
                    'error' => $validationError->getMessage(),
                ]);

                if ($willCorrect) {
                    $prompt = $repairPrompt($validationError->getMessage(), $result);
                }
            }
        }

        throw new ArtifactValidationException(
            $lastValidationError?->getMessage() ?? "Boiler {$artifact} validation failed without an error.",
            previous: $lastValidationError,
        );
    }

    /**
     * Mark this job failed without consuming another attempt.
     *
     * Semantic failures are terminal, so the queue must not replay them; transport
     * failures still bubble out of handle() and keep their retry. Outside a queue
     * context there is nothing to mark, so the caller gets the exception instead.
     */
    private function failTerminally(Throwable $throwable): void
    {
        if ($this->job === null) {
            throw $throwable;
        }

        $this->fail($throwable);
    }
}
