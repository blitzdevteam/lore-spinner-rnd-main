<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\FormatDetectionAgent;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Pipeline Upgrade V2.2 — Format detection runs after IP trim merge and before IP Audit.
 * Routes Voice Lock to Deliverable 1A (NOVEL) or 1B (SCREENPLAY).
 */
final class FormatDetectionJob implements ShouldQueue
{
    use Queueable;

    /** A typical printed novel page. */
    private const int WORDS_PER_PRINTED_PAGE = 300;

    /** A typical formatted screenplay page (about one screen minute). */
    private const int WORDS_PER_SCREENPLAY_PAGE = 180;

    public int $tries = 3;

    public int $timeout = 300;

    public int $backoff = 60;

    public function __construct(
        private Story $story,
    ) {
        $this->onQueue('adaptation');
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $adaptation = $this->story->adaptation;

        try {
            $adaptation->update([
                'adaptation_status' => AdaptationStatusEnum::FORMAT_DETECTION,
            ]);

            $ipTrimming = $adaptation->ip_trimming ?? [];
            $source = ! empty($ipTrimming['trimmed_source_text']['text'])
                ? $ipTrimming['trimmed_source_text']['text']
                : $this->story->getScriptContent();
            $excerpt = mb_substr($source, 0, 8000);

            $response = (new FormatDetectionAgent)->prompt(
                view('ai.agents.adaptation.format-detection.prompt', [
                    'scriptExcerpt' => $excerpt,
                    'sourceFacts' => $this->sourceFacts($source),
                ])->render()
            );

            $adaptation->update([
                'format_detection' => $response->toArray(),
            ]);

            IpAuditJob::dispatch($this->story)->onQueue('adaptation');
        } catch (Throwable $throwable) {
            $adaptation->update([
                'adaptation_status' => AdaptationStatusEnum::FAILED,
            ]);

            throw $throwable;
        }
    }

    /**
     * The whole source's length, measured here, because the agent only reads
     * an excerpt and cannot estimate sessions without it.
     *
     * @return array{chapters: int, words_original: int, words_after_trimming: int, estimated_printed_pages: int, estimated_screenplay_pages: int, extracted_events: int}
     */
    private function sourceFacts(string $trimmedSource): array
    {
        $words = static fn (string $text): int => preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’-]*/u', $text);
        $original = $words($this->story->chapters()->pluck('content')->implode("\n"));

        return [
            'chapters' => $this->story->chapters()->count(),
            'words_original' => $original,
            'words_after_trimming' => $words($trimmedSource),
            'estimated_printed_pages' => (int) round($original / self::WORDS_PER_PRINTED_PAGE),
            'estimated_screenplay_pages' => (int) round($original / self::WORDS_PER_SCREENPLAY_PAGE),
            'extracted_events' => $this->story->events()->count(),
        ];
    }
}
