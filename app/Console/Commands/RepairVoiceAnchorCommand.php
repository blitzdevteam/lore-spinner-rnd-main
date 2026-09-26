<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Adaptation\VoiceAnchorSanitizer;
use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Strip the `session_relevance` pseudo-JSON tail that older Voice Lock merges
 * appended to voice anchor prose, and store the tag as its own key.
 *
 * Only `story_adaptations.voice_profile.voice_anchor` is touched. Running it
 * twice is a no-op. Cached runtime prompts keep the old text until
 * `stories:rebuild-runtime-prompts` runs.
 */
final class RepairVoiceAnchorCommand extends Command
{
    protected $signature = 'stories:repair-voice-anchor
                            {story?* : Story IDs or slugs. Omit to scan every adapted story}
                            {--dry-run : Report corrupted exemplars without writing anything}';

    protected $description = 'Remove session_relevance tails leaked into stored voice anchor prose';

    public function handle(VoiceAnchorSanitizer $sanitizer): int
    {
        $stories = $this->resolveStories();

        if ($stories->isEmpty()) {
            $this->error('No matching stories with an adaptation found.');

            return self::FAILURE;
        }

        $rows = [];
        $repairedStories = 0;

        foreach ($stories as $story) {
            $voiceProfile = (array) ($story->adaptation->voice_profile ?? []);
            $corrupted = $sanitizer->corruptedExemplarCount($voiceProfile);

            if ($corrupted === 0) {
                $rows[] = [$story->slug, 0, 'clean'];

                continue;
            }

            if ($this->option('dry-run')) {
                $rows[] = [$story->slug, $corrupted, 'would repair'];

                continue;
            }

            $story->adaptation->update(['voice_profile' => $sanitizer->sanitizeProfile($voiceProfile)]);
            $repairedStories++;
            $rows[] = [$story->slug, $corrupted, 'repaired'];
        }

        $this->table(['Story', 'Corrupted exemplars', 'Result'], $rows);

        if ($repairedStories > 0) {
            $this->info($repairedStories.' story voice profile(s) repaired. Run stories:rebuild-runtime-prompts for them next.');
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Story>
     */
    private function resolveStories(): Collection
    {
        $identifiers = (array) $this->argument('story');

        if ($identifiers === []) {
            return Story::query()
                ->whereHas('adaptation')
                ->with('adaptation')
                ->orderBy('id')
                ->get();
        }

        return collect($identifiers)
            ->map(fn (string $identifier): ?Story => is_numeric($identifier)
                ? Story::with('adaptation')->find((int) $identifier)
                : Story::with('adaptation')->where('slug', $identifier)->first())
            ->filter(fn (?Story $story): bool => $story?->adaptation !== null)
            ->values();
    }
}
