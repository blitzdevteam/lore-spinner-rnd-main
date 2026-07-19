<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\BoilerDraftContextBuilder;
use App\Ai\Adaptation\BoilerDraftValidator;
use App\Ai\Agents\Adaptation\BoilerDraftAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Models\Story;
use App\Services\Adaptation\SessionEventLoader;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class BoilerDraftJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    public int $timeout = 720;

    public int $backoff = 60;

    public function __construct(
        private Story $story,
        private int $sessionNumber,
    ) {
        $this->onQueue('adaptation');
    }

    /**
     * @throws Throwable
     */
    public function handle(
        BoilerDraftContextBuilder $contextBuilder,
        BoilerDraftValidator $validator,
        SessionEventLoader $eventLoader,
    ): void {
        $adaptation = $this->story->adaptation;
        $session = $adaptation?->sessionAdaptations()
            ->where('session_number', $this->sessionNumber)
            ->firstOrFail();

        if (! $adaptation?->boiler_enabled || ! $session) {
            return;
        }

        if (in_array($session->boiler_status, [BoilerStatus::DRAFTED, BoilerStatus::FINALIZED], true)) {
            return;
        }

        if ($session->boiler_status === BoilerStatus::FAILED) {
            return;
        }

        $session->update([
            'boiler_status' => BoilerStatus::DRAFTING,
            'boiler_error' => null,
        ]);

        try {
            $context = $contextBuilder->build($this->story, $this->sessionNumber);
            $result = (new BoilerDraftAgent)->prompt(
                view('ai.agents.adaptation.boiler-draft.prompt', [
                    'context' => $context,
                ])->render()
            )->toArray();

            $startPosition = isset($session->entry_point_diagnosis['start_event_position'])
                ? (int) $session->entry_point_diagnosis['start_event_position']
                : null;
            $events = $eventLoader->forSession($this->story, $this->sessionNumber, $startPosition);

            $validator->validate(
                $result,
                $session,
                (array) $adaptation->story_session_map,
                $events,
            );

            $session->update([
                'boiler_draft' => $result,
                'boiler_status' => BoilerStatus::DRAFTED,
                'boiler_error' => null,
                'boiler_generated_at' => now(),
            ]);
        } catch (Throwable $throwable) {
            $session->update([
                'boiler_error' => mb_substr($throwable->getMessage(), 0, 2_000),
            ]);

            throw $throwable;
        }
    }

    public function failed(?Throwable $throwable): void
    {
        $adaptation = $this->story->fresh()?->adaptation;
        $session = $adaptation?->sessionAdaptations()
            ->where('session_number', $this->sessionNumber)
            ->first();

        $session?->update([
            'boiler_status' => BoilerStatus::FAILED,
            'boiler_error' => mb_substr($throwable?->getMessage() ?? 'Boiler Draft failed.', 0, 2_000),
            'session_status' => SessionAdaptationStatusEnum::FAILED,
        ]);
    }
}
