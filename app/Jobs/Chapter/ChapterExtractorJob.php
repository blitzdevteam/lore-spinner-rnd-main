<?php

declare(strict_types=1);

namespace App\Jobs\Chapter;

use App\Ai\Agents\ChapterExtractorAgent;
use App\Enums\Chapter\ChapterStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Helpers\LineNumberFormatterHelper;
use App\Helpers\NumberedLineExtractorHelper;
use App\Models\Story;
use App\Support\ScriptChapterBoundaryDetector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ChapterExtractorJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 660;

    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Story $story,
    ) {
        $this->onQueue('chapter-extraction');
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        try {
            $this->story->update([
                'status' => StoryStatusEnum::EXTRACTING_CHAPTERS,
            ]);

            $rawScript = file_get_contents($this->story->getFirstMediaPath('script'));
            $rawLines = explode("\n", str_replace("\r\n", "\n", $rawScript));
            $structuredChapters = ScriptChapterBoundaryDetector::detect($rawScript);

            if ($structuredChapters !== null) {
                DB::transaction(function () use ($structuredChapters, $rawLines): void {
                    foreach ($structuredChapters as $chapter) {
                        $this->story->chapters()->create([
                            'title' => $chapter['title'],
                            'position' => $chapter['position'],
                            'teaser' => $chapter['teaser'],
                            'status' => ChapterStatusEnum::AWAITING_CREATOR_REVIEW,
                            'content' => ScriptChapterBoundaryDetector::extractPlainSegment(
                                $rawLines,
                                $chapter['start_line'],
                                $chapter['end_line'],
                            ),
                        ]);
                    }

                    $this->story->update([
                        'status' => StoryStatusEnum::DRAFT,
                    ]);
                });

                return;
            }

            $linedContent = LineNumberFormatterHelper::handle($rawScript);

            $response = new ChapterExtractorAgent()
                ->prompt(
                    view('ai.agents.chapter-extractor.prompt', [
                        'content' => $linedContent['content'],
                    ])->render()
                );

            DB::transaction(function () use ($linedContent, $response): void {
                foreach ($response['chapters'] as $chapter) {
                    $this->story->chapters()->create([
                        'title' => $chapter['title'],
                        'position' => $chapter['position'],
                        'teaser' => $chapter['teaser'],
                        'status' => ChapterStatusEnum::AWAITING_CREATOR_REVIEW,
                        'content' => NumberedLineExtractorHelper::handle(
                            $linedContent['content'],
                            $chapter['start_line'],
                            $chapter['end_line']
                        ),
                    ]);
                }

                $this->story->update([
                    'status' => StoryStatusEnum::DRAFT,
                ]);
            });
        } catch (Throwable $throwable) {
            $this->story->update([
                'status' => StoryStatusEnum::AWAITING_EXTRACTING_CHAPTERS_REQUEST,
            ]);

            throw $throwable;
        }
    }
}
