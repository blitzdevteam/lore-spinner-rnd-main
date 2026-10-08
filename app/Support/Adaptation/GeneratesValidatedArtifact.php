<?php

declare(strict_types=1);

namespace App\Support\Adaptation;

use App\Exceptions\Adaptation\ArtifactValidationException;
use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Step;
use Laravel\Ai\Responses\TextResponse;
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

    /** Seconds kept free at the end of the job for saving the result and logging. */
    private const int TIME_RESERVE_SECONDS = 45;

    /** @var array<string, mixed> Usage of the latest model response, for the generation log. */
    private array $lastUsage = [];

    /**
     * A repair attempt that cannot finish before the job's time limit gets the
     * worker killed, which loses the error and burns a queue attempt. Stop
     * cleanly instead and report the last validation error.
     */
    private function hasTimeForAnotherAttempt(float $startedAt, float $longestAttempt): bool
    {
        $limit = (int) ($this->timeout ?? 0);

        if ($limit <= 0) {
            return true;
        }

        return (microtime(true) - $startedAt) + $longestAttempt * 1.2 + self::TIME_RESERVE_SECONDS < $limit;
    }

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
        $startedAt = microtime(true);
        $longestAttempt = 0.0;

        for ($attempt = 1; $attempt <= self::MAX_VALIDATION_ATTEMPTS; $attempt++) {
            if ($attempt > 1 && ! $this->hasTimeForAnotherAttempt($startedAt, $longestAttempt)) {
                Log::warning("boiler.{$artifact}_out_of_time", [
                    ...$logContext,
                    'generation_attempt' => $attempt,
                    'elapsed_seconds' => (int) (microtime(true) - $startedAt),
                    'longest_attempt_seconds' => (int) $longestAttempt,
                ]);

                break;
            }

            $attemptStartedAt = microtime(true);
            $result = $generate($prompt);
            $longestAttempt = max($longestAttempt, microtime(true) - $attemptStartedAt);

            Log::info("boiler.{$artifact}_generated", [
                ...$logContext,
                'generation_attempt' => $attempt,
                'seconds' => round(microtime(true) - $attemptStartedAt, 1),
                ...$this->lastUsage,
            ]);

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
     * A response cut off by the output-token limit parses as valid JSON with whole
     * sections missing, so it is never accepted as a finished artifact.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    private function completeArtifact(TextResponse $response, string $artifact): array
    {
        $this->lastUsage = [
            'model' => $response->meta->model,
            'input_tokens' => $response->usage->promptTokens,
            'cached_input_tokens' => $response->usage->cacheReadInputTokens,
            'output_tokens' => $response->usage->completionTokens,
            'reasoning_tokens' => $response->usage->reasoningTokens,
        ];

        if ($response->steps->contains(fn (Step $step): bool => $step->finishReason === FinishReason::Length)) {
            throw new RuntimeException("Boiler {$artifact} ran out of output tokens before it finished, so the response is incomplete.");
        }

        return method_exists($response, 'toArray') ? $response->toArray() : [];
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
