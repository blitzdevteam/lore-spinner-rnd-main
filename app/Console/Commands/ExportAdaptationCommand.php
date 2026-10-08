<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Story;
use App\Models\StoryAdaptation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Export adaptation results to CSV and JSON for a given story.
 *
 * Writes the complete Boiler output (story-wide artifacts, every session's
 * phases, scene cards and runtime prompts) to the public disk under exports/,
 * so it can be downloaded from the Cloud file explorer.
 */
final class ExportAdaptationCommand extends Command
{
    protected $signature = 'adaptation:export
        {story? : Story ID or slug (defaults to latest)}
        {--path= : Write into this local directory instead of the public disk}
        {--disk=public : Filesystem disk when --path is omitted (public = Cloud File explorer)}';

    protected $description = 'Export adaptation pipeline results to CSV + JSON';

    public function handle(): int
    {
        $story = $this->resolveStory();

        if (! $story) {
            $this->error('Story not found.');

            return self::FAILURE;
        }

        $adaptation = $story->adaptation;

        if (! $adaptation) {
            $this->error("No adaptation data for \"{$story->title}\".");

            return self::FAILURE;
        }

        $adaptation->load('sessionAdaptations');

        $slug = $story->slug ?? 'story-'.$story->id;
        $timestamp = now()->format('Y-m-d_His');
        $csvName = "adaptation-{$slug}-{$timestamp}.csv";
        $jsonName = "adaptation-{$slug}-{$timestamp}-full.json";
        $csv = $this->csv($adaptation);
        $json = $this->json($story, $adaptation);

        if ($this->option('path')) {
            $dir = (string) $this->option('path');
            File::ensureDirectoryExists($dir);
            File::put("{$dir}/{$csvName}", $csv);
            File::put("{$dir}/{$jsonName}", $json);
            $this->info("Exported for: {$story->title}");
            $this->line("  CSV:  {$dir}/{$csvName}");
            $this->line("  JSON: {$dir}/{$jsonName} (".mb_strlen($json).' bytes)');
        } else {
            $disk = (string) $this->option('disk');
            Storage::disk($disk)->put("exports/{$csvName}", $csv);
            Storage::disk($disk)->put("exports/{$jsonName}", $json);
            $this->info("Exported for: {$story->title}");
            $this->line("  CSV:  exports/{$csvName} on disk [{$disk}]");
            $this->line("  JSON: exports/{$jsonName} on disk [{$disk}] (".mb_strlen($json).' bytes)');
            $this->line('Download both from the Cloud file explorer under exports/.');
        }

        return self::SUCCESS;
    }

    private function resolveStory(): ?Story
    {
        $identifier = $this->argument('story');

        if (! $identifier) {
            return Story::latest()->first();
        }

        if (is_numeric($identifier)) {
            return Story::find((int) $identifier);
        }

        return Story::where('slug', $identifier)->first();
    }

    private function csv(StoryAdaptation $adaptation): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'story_status',
            'boiler_enabled',
            'format_detection',
            'ip_audit',
            'voice_profile',
            'story_session_map',
        ]);
        fputcsv($handle, [
            $adaptation->adaptation_status->value,
            $adaptation->boiler_enabled ? 'yes' : 'no',
            $adaptation->format_detection ? 'done' : 'pending',
            $adaptation->ip_audit ? 'done' : 'pending',
            $adaptation->voice_profile ? 'done' : 'pending',
            $adaptation->story_session_map ? 'done' : 'pending',
        ]);

        fputcsv($handle, []);

        fputcsv($handle, [
            'session_number',
            'session_status',
            'boiler_status',
            'entry_point_diagnosis',
            'session_architecture',
            'choice_design',
            'consequence_map',
            'session_close',
            'editorial_verification',
            'boiler_final_cut',
            'updated_at',
        ]);

        foreach ($adaptation->sessionAdaptations->sortBy('session_number') as $session) {
            fputcsv($handle, [
                $session->session_number,
                $session->session_status->value,
                $session->boiler_status->value,
                $session->entry_point_diagnosis ? 'done' : 'pending',
                $session->session_architecture ? 'done' : 'pending',
                $session->session_choice_design ? 'done' : 'pending',
                $session->choice_consequence_map ? 'done' : 'pending',
                $session->session_close_design ? 'done' : 'pending',
                $session->editorial_verification ? 'done' : 'pending',
                $session->boiler_final_cut ? 'done' : 'pending',
                $session->updated_at?->toDateTimeString(),
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function json(Story $story, StoryAdaptation $adaptation): string
    {
        $data = [
            'story' => [
                'id' => $story->id,
                'title' => $story->title,
                'slug' => $story->slug,
            ],
            'exported_at' => now()->toIso8601String(),
            'adaptation_status' => $adaptation->adaptation_status->value,
            'boiler_enabled' => (bool) $adaptation->boiler_enabled,
            'story_wide' => [
                'format_detection' => $adaptation->format_detection,
                'ip_audit' => $adaptation->ip_audit,
                'voice_profile' => $adaptation->voice_profile,
                'story_session_map' => $adaptation->story_session_map,
            ],
            'sessions' => $adaptation->sessionAdaptations
                ->sortBy('session_number')
                ->map(fn ($s) => [
                    'session_number' => $s->session_number,
                    'session_status' => $s->session_status->value,
                    'boiler_status' => $s->boiler_status->value,
                    'boiler_error' => $s->boiler_error,
                    'entry_point_diagnosis' => $s->entry_point_diagnosis,
                    'boiler_draft' => $s->boiler_draft,
                    'session_architecture' => $s->session_architecture,
                    'session_choice_design' => $s->session_choice_design,
                    'choice_consequence_map' => $s->choice_consequence_map,
                    'session_close_design' => $s->session_close_design,
                    'boiler_final_cut' => $s->boiler_final_cut,
                    'editorial_verification' => $s->editorial_verification,
                    'runtime_narrator_prompt' => $s->runtime_narrator_prompt,
                    'updated_at' => $s->updated_at?->toIso8601String(),
                ])
                ->values()
                ->toArray(),
        ];

        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
