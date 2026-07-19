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
 * Size policy (warn-not-fail):
 *   The builder runs a 6-pass compression cascade. If the prompt fits under
 *   MAX_PROMPT_CHARS it is returned from that pass. If all passes exceed the cap
 *   the most-compressed version is returned with a warning logged. Either way
 *   this job always persists — adaptation is never blocked by prompt size.
 *   Check the narration log after adaptation for near_cap / over_cap entries.
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
        $adaptation = $this->story->adaptation;
        $session    = $adaptation->sessionAdaptations()->where('session_number', $this->sessionNumber)->firstOrFail();

        $session->update(['session_status' => SessionAdaptationStatusEnum::RUNTIME_ASSEMBLY]);

        // build() always returns a string — it logs warnings but never throws
        // for size or missing anchor fields. Non-assembly exceptions (DB, render
        // errors) still bubble up and fail the job naturally.
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
        ]);
    }

    public function failed(?Throwable $throwable): void
    {
        $session = $this->story->fresh()?->adaptation?->sessionAdaptations()
            ->where('session_number', $this->sessionNumber)
            ->first();

        $session?->update([
            'session_status' => SessionAdaptationStatusEnum::FAILED,
        ]);

        \Log::channel('narration')->error('runtime_narrator_assembly.failed', [
            'story_id' => $this->story->id,
            'session_number' => $this->sessionNumber,
            'error' => $throwable?->getMessage(),
        ]);
    }
}
