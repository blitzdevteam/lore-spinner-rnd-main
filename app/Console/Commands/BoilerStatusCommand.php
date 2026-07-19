<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Story;
use Illuminate\Console\Command;

final class BoilerStatusCommand extends Command
{
    protected $signature = 'stories:boiler-status
                            {story : Story ID or slug}
                            {--session= : Limit output to one session number}';

    protected $description = 'Show Boiler compilation status for a story';

    public function handle(): int
    {
        $story = $this->resolveStory();
        if ($story === null || $story->adaptation === null) {
            $this->error('Story or adaptation not found.');

            return self::FAILURE;
        }

        $sessions = $story->adaptation->sessionAdaptations()
            ->withCount('playableCut')
            ->when(
                $this->option('session') !== null,
                fn ($query) => $query->where('session_number', (int) $this->option('session')),
            )
            ->orderBy('session_number')
            ->get();

        $this->line('Story: ' . $story->title);
        $this->line('Boiler enabled: ' . ($story->adaptation->boiler_enabled ? 'yes' : 'no'));
        $this->line('Adaptation status: ' . $story->adaptation->adaptation_status->value);
        $this->table(
            ['Session', 'Session status', 'Boiler status', 'Cut', 'Moments', 'Prompt chars', 'Error'],
            $sessions->map(function ($session): array {
                $cut = $session->playableCut()->withCount('moments')->first();

                return [
                    $session->session_number,
                    $session->session_status->value,
                    $session->boiler_status->value,
                    $cut?->status ?? 'none',
                    $cut?->moments_count ?? 0,
                    mb_strlen((string) $session->runtime_narrator_prompt),
                    $session->boiler_error ?? '',
                ];
            })->all(),
        );

        return self::SUCCESS;
    }

    private function resolveStory(): ?Story
    {
        $identifier = (string) $this->argument('story');

        return is_numeric($identifier)
            ? Story::find((int) $identifier)
            : Story::where('slug', $identifier)->first();
    }
}
