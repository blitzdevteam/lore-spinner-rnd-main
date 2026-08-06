<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\RuntimeNarratorPromptValidator;
use App\Ai\Adaptation\RuntimeNarratorTemplateBuilder;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Story;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Pipeline Upgrade V2 — Deliverable 8 assembly job.
 *
 * Runs as the LAST step in each per-session chain (after EditorialVerificationJob).
 * Assembles the 18-section D8 v2 runtime narrator prompt from every prior phase
 * output and persists it on `session_adaptations.runtime_narrator_prompt`.
 * Chaos Mode reads this column at session start.
 *
 * The builder may log soft quality/size warnings. This job validates the
 * mode-specific structural contract and persists only a valid prompt, then
 * becomes the sole owner of session_status=completed.
 */
final class RuntimeNarratorAssemblyJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public int $backoff = 30;

    public function __construct(
        private Story $story,
        private int $sessionNumber,
    ) {
        $this->onQueue('adaptation');
    }

    /**
     * @throws Throwable
     */
    public function handle(
        RuntimeNarratorTemplateBuilder $builder,
        RuntimeNarratorPromptValidator $validator,
    ): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $adaptation = $this->story->adaptation;
        $session    = $adaptation->sessionAdaptations()->where('session_number', $this->sessionNumber)->firstOrFail();

        $session->update(['session_status' => SessionAdaptationStatusEnum::RUNTIME_ASSEMBLY]);

        $prompt    = $builder->build($this->story, $session);
        $charCount = mb_strlen($prompt);
        $validator->validate($prompt, (bool) $adaptation->boiler_enabled);

        \Log::channel('narration')->info('runtime_narrator_assembly.assembled', [
            'story_id'       => $this->story->id,
            'session_number' => $this->sessionNumber,
            'char_count'     => $charCount,
            'cap'            => RuntimeNarratorTemplateBuilder::MAX_PROMPT_CHARS,
            'over_cap'       => $charCount > RuntimeNarratorTemplateBuilder::MAX_PROMPT_CHARS,
        ]);

        $session->update([
            'runtime_narrator_prompt'       => $prompt,
            'runtime_narrator_assembled_at' => Carbon::now(),
            'session_status'                => SessionAdaptationStatusEnum::COMPLETED,
            // Clear any error left by an earlier failed assembly attempt.
            'boiler_error'                  => null,
        ]);
    }

    public function failed(?Throwable $throwable): void
    {
        $session = $this->story->fresh()?->adaptation?->sessionAdaptations()
            ->where('session_number', $this->sessionNumber)
            ->first();

        $updates = ['session_status' => SessionAdaptationStatusEnum::FAILED];
        if ($session?->storyAdaptation?->boiler_enabled) {
            $updates['boiler_error'] = mb_substr(
                $throwable?->getMessage() ?? 'Runtime narrator assembly failed.',
                0,
                2_000,
            );
        }
        $session?->update($updates);

        \Log::channel('narration')->error('runtime_narrator_assembly.failed', [
            'story_id' => $this->story->id,
            'session_number' => $this->sessionNumber,
            'error' => $throwable?->getMessage(),
        ]);
    }
}
