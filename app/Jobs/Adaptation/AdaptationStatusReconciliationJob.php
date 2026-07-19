<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\RuntimeNarratorPromptValidator;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class AdaptationStatusReconciliationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        private Story $story,
    ) {
        $this->onQueue('adaptation');
    }

    public function handle(RuntimeNarratorPromptValidator $promptValidator): void
    {
        $adaptation = $this->story->adaptation;

        if (! $adaptation) {
            return;
        }

        $sessions = $adaptation->sessionAdaptations()->with('playableCut.moments')->get();

        if ($sessions->isEmpty()) {
            return;
        }

        $completed = $sessions->where('session_status', SessionAdaptationStatusEnum::COMPLETED)->count();
        $failed = $sessions->where('session_status', SessionAdaptationStatusEnum::FAILED)->count();
        $total = $sessions->count();

        // Pipeline Upgrade V2: a session is only fully COMPLETED when its
        // RuntimeNarratorAssemblyJob has cached `runtime_narrator_prompt`. If
        // every session_status is COMPLETED but any one of them is missing
        // the assembled prompt, treat the run as PARTIAL_COMPLETION so the
        // editor can re-run the assembly job manually.
        $invalidRuntimeResult = $sessions
            ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
            ->filter(function ($session) use ($adaptation, $promptValidator): bool {
                $prompt = (string) ($session->runtime_narrator_prompt ?? '');

                try {
                    $promptValidator->validate($prompt, (bool) $adaptation->boiler_enabled);
                } catch (\RuntimeException) {
                    return true;
                }

                if (! $adaptation->boiler_enabled) {
                    return false;
                }

                return $session->boiler_status !== BoilerStatus::FINALIZED
                    || $session->playableCut === null
                    || $session->playableCut->status !== 'finalized'
                    || $session->playableCut->cut_key !== 'standard'
                    || $session->playableCut->moments->isEmpty();
            })
            ->count();

        $status = match (true) {
            $completed === $total && $invalidRuntimeResult === 0 => AdaptationStatusEnum::COMPLETED,
            $failed === $total => AdaptationStatusEnum::FAILED,
            default => AdaptationStatusEnum::PARTIAL_COMPLETION,
        };

        $adaptation->update(['adaptation_status' => $status]);
    }
}
