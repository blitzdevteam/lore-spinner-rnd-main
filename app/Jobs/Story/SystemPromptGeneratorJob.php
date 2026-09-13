<?php

declare(strict_types=1);

namespace App\Jobs\Story;

use App\Ai\Agents\SystemPromptGeneratorAgent;
use App\Models\Story;
use App\Support\LaunchCatalog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

final class SystemPromptGeneratorJob implements ShouldQueue
{
    use Queueable;

    /** Keeps system-prompt generation within model context for full-length classics. */
    private const int MAX_SCRIPT_CHARS = 80_000;

    public int $tries = 3;

    public int $timeout = 300;

    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Story $story,
    ) {
        $this->onQueue('system-prompt-generation');
    }

    /**
     * Execute the job.
     *
     * @throws Throwable
     */
    public function handle(): void
    {
        try {
            $scriptPath = $this->story->getFirstMediaPath('script');

            if (! $scriptPath || ! file_exists($scriptPath)) {
                Log::warning("SystemPromptGeneratorJob: No script found for story [{$this->story->id}].");

                return;
            }

            $scriptContent = file_get_contents($scriptPath);
            $scriptExcerpt = $this->scriptExcerptForPrompt($scriptContent);
            $playableProtagonist = LaunchCatalog::protagonist($this->story->slug);

            /** @var StructuredAgentResponse $response */
            $response = (new SystemPromptGeneratorAgent)->prompt(
                view('ai.agents.system-prompt-generator.prompt', [
                    'title' => $this->story->title,
                    'script' => $scriptExcerpt,
                    'playableProtagonist' => $playableProtagonist,
                    'scriptTruncated' => mb_strlen($scriptContent) > mb_strlen($scriptExcerpt),
                ])->render()
            );

            $characterName = $playableProtagonist !== null
                ? mb_strtoupper($playableProtagonist)
                : ($response['playable_character_name'] ?? null);

            // Store the extracted data as JSON — the system prompt is rendered at runtime
            $this->story->update([
                'system_prompt' => [
                    'character_name' => $characterName,
                    'world_rules' => $response['world_rules'] ?? [],
                    'tone_and_style' => $response['tone_and_style'] ?? null,
                ],
            ]);

            Log::info("SystemPromptGeneratorJob: Generated system prompt data for story [{$this->story->id}] — character: {$response['playable_character_name']}, rules: ".count($response['world_rules'] ?? []));
        } catch (Throwable $throwable) {
            Log::error("SystemPromptGeneratorJob: Failed for story [{$this->story->id}]: {$throwable->getMessage()}");

            throw $throwable;
        }
    }

    private function scriptExcerptForPrompt(string $fullScript): string
    {
        if (mb_strlen($fullScript) <= self::MAX_SCRIPT_CHARS) {
            return $fullScript;
        }

        return mb_substr($fullScript, 0, self::MAX_SCRIPT_CHARS)
            ."\n\n[Script truncated for analysis — opening portion only.]";
    }
}
