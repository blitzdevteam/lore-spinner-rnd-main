<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\StoryAdaptation;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class BoilerDiagnoseCommand extends Command
{
    protected $signature = 'stories:boiler-diagnose
                            {story : Story ID or slug}
                            {--disk=public : Filesystem disk for upload (public = Cloud File explorer)}
                            {--path= : Optional local filesystem path instead of disk upload}';

    protected $description = 'Export Boiler pipeline diagnostic JSON (sessions, failures, queue, boiler.* logs) for model review';

    public function handle(): int
    {
        $story = $this->resolveStory();
        if ($story === null || $story->adaptation === null) {
            $this->error('Story or adaptation not found.');

            return self::FAILURE;
        }

        $adaptation = $story->adaptation()->with([
            'sessionAdaptations.playableCut.moments',
            'sessionAdaptations.playableCut.eventRoles',
        ])->firstOrFail();

        try {
            $pack = $this->buildPack($story, $adaptation);
            $json = json_encode(
                $pack,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE,
            );

            if ($json === false) {
                throw new \RuntimeException('json_encode failed: '.json_last_error_msg());
            }
        } catch (Throwable $e) {
            $this->error($e::class.': '.$e->getMessage());

            return self::FAILURE;
        }

        $filename = sprintf('%s-boiler-opus-diagnostic.json', $story->slug);

        if ($this->option('path')) {
            $path = rtrim((string) $this->option('path'), '/');
            if (is_dir($path)) {
                $path = $path.'/'.$filename;
            }
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            file_put_contents($path, $json);
            $this->info("Wrote {$path} (".strlen($json).' bytes)');
        } else {
            $disk = (string) $this->option('disk');
            $remote = 'exports/'.$filename;
            Storage::disk($disk)->put($remote, $json);
            $this->info("Uploaded {$remote} on disk [{$disk}] (".strlen($json).' bytes)');
            $this->line('Download via Cloud File explorer under exports/');
        }

        $this->line('sessions='.count($pack['sessions']));
        $this->line('failed_matched='.count($pack['queue']['failed_jobs_matched']));
        $this->line('log_hits='.$pack['logs']['hit_count']);
        $this->line('queue pending='.$pack['queue']['adaptation_pending'].' reserved='.$pack['queue']['adaptation_reserved']);

        foreach ($pack['sessions'] as $session) {
            $this->line(sprintf(
                'S%d %s/%s draft=%s cut=%s runtime=%s',
                $session['session_number'],
                $session['session_status'],
                $session['boiler_status'] ?? 'null',
                $session['artifacts_present']['draft'] ? 'y' : 'n',
                $session['artifacts_present']['final_cut'] ? 'y' : 'n',
                $session['artifacts_present']['runtime_prompt'] ? 'y' : 'n',
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPack(Story $story, StoryAdaptation $adaptation): array
    {
        $storyId = $story->id;
        $slug = (string) $story->slug;
        $ssm = $adaptation->story_session_map;
        $alloc = collect(data_get($ssm, 'session_allocation', []));

        $sessions = $adaptation->sessionAdaptations
            ->sortBy('session_number')
            ->values()
            ->map(fn (SessionAdaptation $session): array => $this->packSession($session, $alloc))
            ->all();

        $eventCountBySession = [];
        foreach ($adaptation->sessionAdaptations->pluck('session_number') as $n) {
            $eventCountBySession[(int) $n] = $story->events()->where('session_number', (int) $n)->count();
        }

        return [
            'brief_for_model' => [
                'goal' => 'Diagnose why this story Boiler adaptation keeps failing while Speckled Band completed cleanly.',
                'slug' => $slug,
                'story_id' => $storyId,
                'known_ops_noise' => [
                    'Laravel Cloud deploys killed workers mid-Finalize (attempts inflated → MaxAttemptsExceeded).',
                    'Tinker finally-closures orphaned Bus batches (batch pending jobs with empty jobs table).',
                    'Release reserved jobs MUST reset attempts to 0.',
                    'Do not treat MaxAttemptsExceeded as a content bug.',
                ],
                'known_content_failures_seen' => [
                    'Finalize: invalid word or turn budgets on scene cards.',
                    'Editorial: choice_ids vs Phase 5 authority; weak playable rooms; SESSION_END_HOOK binding.',
                    'Draft: duplicate source_event_ids across moments; target_moment_count outside SSM range.',
                ],
                'ask' => 'Separate ops/retry damage from true prompt/validator defects. Propose the smallest durable code/prompt fixes.',
            ],
            'exported_at' => now()->toIso8601String(),
            'story' => [
                'id' => $storyId,
                'title' => $story->title,
                'slug' => $slug,
                'event_count_total' => $story->events()->count(),
                'event_count_by_session' => $eventCountBySession,
            ],
            'adaptation' => [
                'adaptation_status' => $adaptation->adaptation_status->value,
                'boiler_enabled' => (bool) $adaptation->boiler_enabled,
                'has_voice_profile' => ! empty($adaptation->voice_profile),
                'has_story_session_map' => ! empty($ssm),
                'boiling_profile' => data_get($ssm, 'boiling_profile'),
                'session_allocation' => data_get($ssm, 'session_allocation'),
            ],
            'queue' => $this->packQueue($storyId, $slug),
            'sessions' => $sessions,
            'logs' => $this->packLogs($storyId),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $alloc
     * @return array<string, mixed>
     */
    private function packSession(SessionAdaptation $session, Collection $alloc): array
    {
        $n = (int) $session->session_number;
        $guidance = $alloc->firstWhere('session_number', $n)
            ?? $alloc->first(fn ($row): bool => (int) data_get($row, 'session_number') === $n);

        $draft = is_array($session->boiler_draft) ? $session->boiler_draft : null;
        $cut = is_array($session->boiler_final_cut) ? $session->boiler_final_cut : null;
        $choices = is_array($session->session_choice_design) ? $session->session_choice_design : null;
        $arch = is_array($session->session_architecture) ? $session->session_architecture : null;

        $momentDrafts = [];
        $sourceIdHits = [];
        foreach ((array) data_get($draft, 'moment_drafts', []) as $md) {
            if (! is_array($md)) {
                continue;
            }
            $ids = array_values(array_map('intval', (array) ($md['source_event_ids'] ?? [])));
            foreach ($ids as $eid) {
                $sourceIdHits[$eid] = ($sourceIdHits[$eid] ?? 0) + 1;
            }
            $momentDrafts[] = [
                'moment_key' => $md['moment_key'] ?? null,
                'anchor_event_id' => $md['anchor_event_id'] ?? null,
                'source_event_ids' => $ids,
                'role' => $md['role'] ?? ($md['moment_role'] ?? null),
            ];
        }
        $duplicateSourceIds = collect($sourceIdHits)->filter(fn (int $c): bool => $c > 1)->keys()->values()->all();

        $branching = [];
        foreach ((array) data_get($choices, 'branching_choices', []) as $choice) {
            if (! is_array($choice)) {
                continue;
            }
            $branching[] = [
                'choice_id' => $choice['choice_id'] ?? null,
                'category' => $choice['category'] ?? null,
                'moment_key' => $choice['moment_key'] ?? null,
            ];
        }

        $finalMoments = [];
        foreach ((array) data_get($cut, 'moments', data_get($cut, 'playable_moments', [])) as $fm) {
            if (! is_array($fm)) {
                continue;
            }
            $card = is_array($fm['scene_card'] ?? null) ? $fm['scene_card'] : $fm;
            $finalMoments[] = [
                'moment_key' => $fm['moment_key'] ?? ($card['moment_key'] ?? null),
                'choice_ids' => $card['choice_ids'] ?? ($fm['choice_ids'] ?? null),
                'word_budget' => $card['word_budget'] ?? null,
                'turn_budget' => $card['turn_budget'] ?? null,
                'next_moment_key' => $card['next_moment_key'] ?? null,
                'exit_condition_len' => isset($card['exit_condition']) ? mb_strlen((string) $card['exit_condition']) : null,
                'affordances_count' => is_array($card['player_affordances'] ?? null) ? count($card['player_affordances']) : null,
            ];
        }

        $normMoments = [];
        $playable = $session->playableCut;
        if ($playable !== null) {
            foreach ($playable->moments->sortBy('moment_order') as $moment) {
                $card = is_array($moment->scene_card) ? $moment->scene_card : [];
                $normMoments[] = [
                    'moment_key' => $moment->moment_key,
                    'moment_order' => $moment->moment_order,
                    'anchor_event_id' => $moment->anchor_event_id,
                    'source_event_ids' => $moment->source_event_ids,
                    'choice_ids' => $card['choice_ids'] ?? null,
                    'word_budget' => $card['word_budget'] ?? null,
                    'turn_budget' => $card['turn_budget'] ?? null,
                    'next_moment_key' => $card['next_moment_key'] ?? null,
                ];
            }
        }

        $archBranching = collect((array) data_get($arch, 'beat_map', data_get($arch, 'beats', [])))
            ->filter(function ($beat): bool {
                if (! is_array($beat)) {
                    return false;
                }
                $slot = strtoupper((string) data_get($beat, 'choice_slot', data_get($beat, 'slot', '')));

                return str_contains($slot, 'BRANCH') || data_get($beat, 'is_branching') === true;
            })
            ->map(fn ($beat): array => [
                'moment_key' => data_get($beat, 'moment_key'),
                'choice_slot' => data_get($beat, 'choice_slot', data_get($beat, 'slot')),
            ])
            ->values()
            ->all();

        return [
            'session_number' => $n,
            'session_status' => $session->session_status->value,
            'boiler_status' => $session->boiler_status?->value,
            'boiler_error' => $session->boiler_error,
            'timestamps' => [
                'boiler_generated_at' => $session->boiler_generated_at?->toIso8601String(),
                'boiler_finalized_at' => $session->boiler_finalized_at?->toIso8601String(),
                'runtime_narrator_assembled_at' => $session->runtime_narrator_assembled_at?->toIso8601String(),
                'updated_at' => $session->updated_at?->toIso8601String(),
            ],
            'artifacts_present' => [
                'entry_point' => ! empty($session->entry_point_diagnosis),
                'draft' => ! empty($draft),
                'architecture' => ! empty($arch),
                'choice_design' => ! empty($choices),
                'consequence_map' => ! empty($session->choice_consequence_map),
                'session_close' => ! empty($session->session_close_design),
                'final_cut' => ! empty($cut),
                'editorial' => ! empty($session->editorial_verification),
                'runtime_prompt' => ! empty($session->runtime_narrator_prompt),
                'normalized_cut' => $playable !== null,
            ],
            'ssm_guidance' => [
                'event_range' => data_get($guidance, 'event_range'),
                'target_playtime_minutes' => data_get($guidance, 'target_playtime_minutes')
                    ?? data_get($guidance, 'pacing_guidance.target_playtime_minutes'),
                'recommended_moment_count_min' => data_get($guidance, 'recommended_moment_count_min')
                    ?? data_get($guidance, 'pacing_guidance.recommended_moment_count_min'),
                'recommended_moment_count_max' => data_get($guidance, 'recommended_moment_count_max')
                    ?? data_get($guidance, 'pacing_guidance.recommended_moment_count_max'),
                'must_compress_event_ids' => data_get($guidance, 'must_compress_event_ids')
                    ?? data_get($guidance, 'pacing_guidance.must_compress_event_ids'),
                'must_breathe_event_ids' => data_get($guidance, 'must_breathe_event_ids')
                    ?? data_get($guidance, 'pacing_guidance.must_breathe_event_ids'),
                'raw_allocation_keys' => is_array($guidance) ? array_keys($guidance) : null,
            ],
            'draft_summary' => $draft ? [
                'target_moment_count' => data_get($draft, 'session_budget_decision.target_moment_count'),
                'target_playtime_minutes' => data_get($draft, 'session_budget_decision.target_playtime_minutes'),
                'moment_draft_count' => count($momentDrafts),
                'moment_drafts' => $momentDrafts,
                'duplicate_source_event_ids' => $duplicateSourceIds,
                'event_role_map_count' => count((array) data_get($draft, 'event_role_map', [])),
            ] : null,
            'architecture_branching_slots' => $archBranching,
            'choice_design_branching' => $branching,
            'final_cut_moment_summaries' => $finalMoments,
            'normalized_moment_summaries' => $normMoments,
            'editorial_verification' => $session->editorial_verification,
            'runtime_prompt_chars' => strlen((string) $session->runtime_narrator_prompt),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function packQueue(int $storyId, string $slug): array
    {
        $failed = [];
        foreach (DB::table('failed_jobs')->orderByDesc('failed_at')->limit(120)->get() as $row) {
            $payload = json_decode((string) $row->payload, true) ?: [];
            $displayName = (string) ($payload['displayName'] ?? '');
            $command = (string) ($payload['data']['command'] ?? '');
            $exception = (string) $row->exception;

            $isAdaptation = str_starts_with($displayName, 'App\\Jobs\\Adaptation\\');
            $matchesStory = str_contains($command, "\"id\";i:{$storyId};")
                || str_contains($command, "story_id\";i:{$storyId};")
                || str_contains($exception, (string) $storyId)
                || str_contains($exception, $slug)
                || str_contains($command, $slug);

            $boilerish = (bool) preg_match(
                '/Boiler|Editorial|Draft|Finalize|source_event|moment_count|SESSION_END|MaxAttemptsExceeded/i',
                $exception,
            );

            if (! $isAdaptation && ! $matchesStory) {
                continue;
            }
            if (! $matchesStory && ! $boilerish) {
                continue;
            }

            $failed[] = [
                'failed_at' => (string) $row->failed_at,
                'uuid' => $row->uuid,
                'displayName' => $displayName,
                'exception_head' => mb_substr(preg_replace('/\s+/', ' ', $exception) ?? $exception, 0, 700),
            ];

            if (count($failed) >= 50) {
                break;
            }
        }

        $jobsNow = [];
        foreach (DB::table('jobs')->where('queue', 'adaptation')->orderBy('id')->get() as $job) {
            $payload = json_decode((string) $job->payload, true) ?: [];
            $jobsNow[] = [
                'id' => $job->id,
                'attempts' => $job->attempts,
                'reserved_at' => $job->reserved_at,
                'available_at' => $job->available_at,
                'available_in_sec' => max(0, (int) $job->available_at - time()),
                'displayName' => $payload['displayName'] ?? null,
            ];
        }

        $batches = [];
        foreach (DB::table('job_batches')->orderByDesc('created_at')->limit(12)->get() as $batch) {
            $batches[] = [
                'id' => $batch->id,
                'name' => $batch->name,
                'total_jobs' => $batch->total_jobs,
                'pending_jobs' => $batch->pending_jobs,
                'failed_jobs' => $batch->failed_jobs,
                'created_at' => $batch->created_at,
                'cancelled_at' => $batch->cancelled_at,
                'finished_at' => $batch->finished_at,
            ];
        }

        return [
            'adaptation_pending' => DB::table('jobs')->where('queue', 'adaptation')->whereNull('reserved_at')->count(),
            'adaptation_reserved' => DB::table('jobs')->where('queue', 'adaptation')->whereNotNull('reserved_at')->count(),
            'jobs_now' => $jobsNow,
            'recent_batches' => $batches,
            'failed_jobs_matched' => $failed,
            'failed_jobs_table_count' => DB::table('failed_jobs')->count(),
        ];
    }

    /**
     * @return array{files_scanned: list<string>, hit_count: int, hits: list<array<string, mixed>>}
     */
    private function packLogs(int $storyId): array
    {
        $hits = [];
        $files = [];
        $patterns = ['boiler.', 'editorial_verification', 'MaxAttemptsExceeded'];

        $paths = array_merge(
            glob(storage_path('logs/laravel*.log')) ?: [],
            glob(storage_path('logs/narration*.log')) ?: [],
        );

        foreach ($paths as $file) {
            $files[] = basename($file);
            $handle = fopen($file, 'r');
            if ($handle === false) {
                continue;
            }

            while (($line = fgets($handle)) !== false) {
                $keep = false;
                foreach ($patterns as $pattern) {
                    if (str_contains($line, $pattern)) {
                        $keep = true;
                        break;
                    }
                }
                if (! $keep) {
                    continue;
                }

                $at = null;
                if (preg_match('/^\[([^\]]+)\]/', $line, $timeMatch) === 1) {
                    $at = $timeMatch[1];
                }

                $level = null;
                $event = null;
                if (preg_match('/\] (?:production|local|staging|cloud)\.(INFO|WARNING|ERROR|DEBUG): ([\w\.]+)/', $line, $match) === 1) {
                    $level = $match[1];
                    $event = $match[2];
                }

                $payload = null;
                $start = strpos($line, '{');
                if ($start !== false) {
                    $decoded = json_decode(substr($line, $start), true);
                    if (is_array($decoded)) {
                        $payload = $decoded;
                    }
                }

                if (is_array($payload) && isset($payload['story_id']) && (int) $payload['story_id'] !== $storyId) {
                    continue;
                }

                $scoped = is_array($payload) && isset($payload['story_id'])
                    || str_contains($line, '"story_id":'.$storyId)
                    || str_contains($line, '"story_id": '.$storyId);

                if (! $scoped && ! str_contains($line, 'boiler.')) {
                    continue;
                }

                $hits[] = [
                    'at' => $at,
                    'level' => $level,
                    'event' => $event,
                    'payload' => $payload,
                    'line_head' => mb_substr(preg_replace('/\s+/', ' ', $line) ?? $line, 0, 400),
                ];
            }

            fclose($handle);
        }

        $hits = array_slice($hits, -250);

        return [
            'files_scanned' => $files,
            'hit_count' => count($hits),
            'hits' => $hits,
        ];
    }

    private function resolveStory(): ?Story
    {
        $identifier = (string) $this->argument('story');

        return is_numeric($identifier)
            ? Story::find((int) $identifier)
            : Story::where('slug', $identifier)->first();
    }
}
