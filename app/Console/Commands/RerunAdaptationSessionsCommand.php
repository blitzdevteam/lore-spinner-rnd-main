<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Jobs\Adaptation\AdaptationStatusReconciliationJob;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Support\Adaptation\SessionAdaptationChain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-run session adaptation from the entry point, keeping the story-level work
 * (trimming, audit, voice profile, Story Session Map). Cancels any unfinished
 * session batches first so two generations never race.
 */
final class RerunAdaptationSessionsCommand extends Command
{
    protected $signature = 'stories:rerun-sessions
                            {story : Story ID or slug}
                            {--session=* : Session numbers. Omit for every session}';

    protected $description = 'Reset and re-run session adaptation from the entry point, keeping the Story Session Map and voice profile';

    public function handle(): int
    {
        $identifier = (string) $this->argument('story');
        $story = (is_numeric($identifier) ? Story::query()->whereKey((int) $identifier) : Story::query()->where('slug', $identifier))
            ->with('adaptation')
            ->first();
        $adaptation = $story?->adaptation;

        if ($adaptation === null || empty($adaptation->story_session_map) || empty($adaptation->voice_profile)) {
            $this->error('The story needs an adaptation with a Story Session Map and a voice profile.');

            return self::FAILURE;
        }

        $requested = array_map('intval', (array) $this->option('session'));
        $sessions = $adaptation->sessionAdaptations()
            ->when($requested !== [], fn ($query) => $query->whereIn('session_number', $requested))
            ->orderBy('session_number')
            ->get();

        if ($sessions->isEmpty()) {
            $this->error('No matching sessions.');

            return self::FAILURE;
        }

        $cancelled = SessionAdaptationChain::cancelUnfinished($story->id);

        $sessions->each(function (SessionAdaptation $session): void {
            $session->playableCut()->delete();
            $session->update([
                'session_status' => SessionAdaptationStatusEnum::PENDING,
                'boiler_status' => BoilerStatus::PENDING,
                'boiler_error' => null,
                'boiler_draft' => null,
                'boiler_final_cut' => null,
                'entry_point_diagnosis' => null,
                'session_architecture' => null,
                'session_choice_design' => null,
                'choice_consequence_map' => null,
                'session_close_design' => null,
                'editorial_verification' => null,
                'runtime_narrator_prompt' => null,
            ]);
        });

        $storyId = $story->id;
        SessionAdaptationChain::dispatchBatch(
            $story,
            $sessions->pluck('session_number')->all(),
            function () use ($storyId): void {
                AdaptationStatusReconciliationJob::dispatch(Story::findOrFail($storyId))->onQueue('adaptation');
            },
        );

        $queued = DB::table('jobs')->where('queue', 'adaptation')->count();
        $this->info("Cancelled {$cancelled} unfinished batch(es). Reset and queued sessions ".$sessions->pluck('session_number')->implode(', ').". Jobs waiting on the adaptation queue: {$queued}.");

        return self::SUCCESS;
    }
}
