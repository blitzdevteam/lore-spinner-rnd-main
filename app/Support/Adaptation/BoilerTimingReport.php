<?php

declare(strict_types=1);

namespace App\Support\Adaptation;

use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\SessionAdaptation;
use App\Models\StoryAdaptation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class BoilerTimingReport
{
    public static function secondsBetween(?Carbon $start, ?Carbon $end): ?int
    {
        if ($start === null || $end === null) {
            return null;
        }

        return (int) $end->diffInSeconds($start, absolute: true);
    }

    public static function humanBetween(?Carbon $start, ?Carbon $end): ?string
    {
        $seconds = self::secondsBetween($start, $end);

        return $seconds === null ? null : self::formatDuration($seconds);
    }

    public static function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = intdiv($seconds, 60);
        $remainder = $seconds % 60;

        if ($minutes < 60) {
            return $remainder === 0
                ? "{$minutes}m"
                : sprintf('%dm %ds', $minutes, $remainder);
        }

        $hours = intdiv($minutes, 60);
        $minutes = $minutes % 60;

        return $minutes === 0
            ? "{$hours}h"
            : sprintf('%dh %dm', $hours, $minutes);
    }

    /**
     * @return array<string, mixed>
     */
    public function forAdaptation(StoryAdaptation $adaptation): array
    {
        $sessions = $adaptation->sessionAdaptations()
            ->with(['playableCut' => fn ($query) => $query->withCount('moments')])
            ->orderBy('session_number')
            ->get()
            ->map(fn (SessionAdaptation $session): array => $this->sessionRow($session))
            ->values()
            ->all();

        return [
            'story_id' => $adaptation->story_id,
            'story_title' => $adaptation->story?->title,
            'story_slug' => $adaptation->story?->slug,
            'adaptation_status' => $adaptation->adaptation_status->value,
            'boiler_enabled' => $adaptation->boiler_enabled,
            'summary' => $this->summarize($sessions),
            'sessions' => $sessions,
        ];
    }

    /**
     * @param  Collection<int, StoryAdaptation>  $adaptations
     * @return array<int, array<string, mixed>>
     */
    public function forAdaptations(Collection $adaptations): array
    {
        return $adaptations
            ->map(fn (StoryAdaptation $adaptation): array => $this->forAdaptation($adaptation))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $sessions
     * @return array<string, mixed>
     */
    private function summarize(array $sessions): array
    {
        $completed = collect($sessions)->filter(
            fn (array $session): bool => $session['runtime_assembled_at'] !== null,
        );

        $fullTrack = $completed->filter(
            fn (array $session): bool => $session['coverage'] === 'full',
        );

        $postDraftOnly = $completed->filter(
            fn (array $session): bool => $session['coverage'] === 'post-draft-only',
        );

        $starts = $completed
            ->map(fn (array $session): ?string => $session['boil_started_at'])
            ->filter()
            ->map(fn (string $timestamp): Carbon => Carbon::parse($timestamp));

        $ends = $completed
            ->map(fn (array $session): ?string => $session['runtime_assembled_at'])
            ->filter()
            ->map(fn (string $timestamp): Carbon => Carbon::parse($timestamp));

        $wallClockSeconds = ($starts->isNotEmpty() && $ends->isNotEmpty())
            ? (int) $ends->max()->diffInSeconds($starts->min(), absolute: true)
            : null;

        $sumSessionSeconds = $completed
            ->pluck('total_seconds')
            ->filter(fn (?int $seconds): bool => $seconds !== null)
            ->sum();

        $avgSessionSeconds = $completed->isEmpty()
            ? null
            : (int) round($sumSessionSeconds / $completed->count());

        return [
            'session_count' => count($sessions),
            'completed_session_count' => $completed->count(),
            'full_track_session_count' => $fullTrack->count(),
            'post_draft_only_session_count' => $postDraftOnly->count(),
            'boil_wall_clock_seconds' => $wallClockSeconds,
            'boil_wall_clock_human' => $wallClockSeconds === null
                ? null
                : self::formatDuration($wallClockSeconds),
            'sum_session_seconds' => $completed->isEmpty() ? null : (int) $sumSessionSeconds,
            'sum_session_human' => $completed->isEmpty()
                ? null
                : self::formatDuration((int) $sumSessionSeconds),
            'avg_session_seconds' => $avgSessionSeconds,
            'avg_session_human' => $avgSessionSeconds === null
                ? null
                : self::formatDuration($avgSessionSeconds),
            'first_boil_activity_at' => $starts->min()?->toIso8601String(),
            'last_boil_completed_at' => $ends->max()?->toIso8601String(),
            'coverage_note' => $this->coverageNote($fullTrack->count(), $postDraftOnly->count(), count($sessions)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionRow(SessionAdaptation $session): array
    {
        $draftStarted = $session->boiler_drafting_started_at;
        $draftCompleted = $session->boiler_generated_at;
        $finalized = $session->boiler_finalized_at;
        $runtimeAssembled = $session->runtime_narrator_assembled_at;

        $boilStarted = $draftStarted ?? $draftCompleted;
        $coverage = $this->coverage($draftStarted, $draftCompleted, $runtimeAssembled);

        return [
            'session_number' => $session->session_number,
            'session_status' => $session->session_status->value,
            'boiler_status' => $session->boiler_status->value,
            'draft_started_at' => $draftStarted?->toIso8601String(),
            'draft_completed_at' => $draftCompleted?->toIso8601String(),
            'finalized_at' => $finalized?->toIso8601String(),
            'runtime_assembled_at' => $runtimeAssembled?->toIso8601String(),
            'boil_started_at' => $boilStarted?->toIso8601String(),
            'draft_seconds' => self::secondsBetween($draftStarted, $draftCompleted),
            'draft_human' => self::humanBetween($draftStarted, $draftCompleted),
            'post_draft_seconds' => self::secondsBetween($draftCompleted, $runtimeAssembled),
            'post_draft_human' => self::humanBetween($draftCompleted, $runtimeAssembled),
            'finalize_seconds' => self::secondsBetween($draftCompleted, $finalized),
            'finalize_human' => self::humanBetween($draftCompleted, $finalized),
            'total_seconds' => self::secondsBetween($boilStarted, $runtimeAssembled),
            'total_human' => self::humanBetween($boilStarted, $runtimeAssembled),
            'coverage' => $coverage,
            'moments' => $session->playableCut?->moments_count ?? 0,
            'prompt_chars' => mb_strlen((string) $session->runtime_narrator_prompt),
            'completed' => $session->session_status === SessionAdaptationStatusEnum::COMPLETED,
        ];
    }

    private function coverage(?Carbon $draftStarted, ?Carbon $draftCompleted, ?Carbon $runtimeAssembled): string
    {
        if ($runtimeAssembled === null) {
            return 'incomplete';
        }

        if ($draftStarted !== null) {
            return 'full';
        }

        if ($draftCompleted !== null) {
            return 'post-draft-only';
        }

        return 'incomplete';
    }

    private function coverageNote(int $fullTrack, int $postDraftOnly, int $totalSessions): string
    {
        if ($fullTrack === $totalSessions && $totalSessions > 0) {
            return 'Full boil timing (draft start through runtime assembly).';
        }

        if ($postDraftOnly > 0) {
            return 'Older sessions lack boiler_drafting_started_at; totals use post-draft timing only for those rows.';
        }

        if ($fullTrack === 0 && $postDraftOnly === 0) {
            return 'No completed boiler sessions yet.';
        }

        return 'Mixed full and post-draft-only session coverage.';
    }
}
