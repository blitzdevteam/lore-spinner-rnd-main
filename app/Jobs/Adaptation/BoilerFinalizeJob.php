<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\BoilerFinalCutValidator;
use App\Ai\Adaptation\BoilerFinalizeContextBuilder;
use App\Ai\Agents\Adaptation\BoilerFinalizeAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Models\Story;
use App\Services\Adaptation\BoilerCutNormalizer;
use App\Services\Adaptation\SessionEventLoader;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class BoilerFinalizeJob implements ShouldQueue
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
        BoilerFinalizeContextBuilder $contextBuilder,
        BoilerFinalCutValidator $validator,
        BoilerCutNormalizer $normalizer,
        SessionEventLoader $eventLoader,
    ): void {
        $adaptation = $this->story->adaptation;
        $session = $adaptation?->sessionAdaptations()
            ->where('session_number', $this->sessionNumber)
            ->firstOrFail();

        if (! $adaptation?->boiler_enabled || ! $session) {
            return;
        }

        if ($session->boiler_status === BoilerStatus::FINALIZED) {
            return;
        }

        if ($session->boiler_status === BoilerStatus::FAILED) {
            return;
        }

        if (! in_array($session->boiler_status, [BoilerStatus::DRAFTED, BoilerStatus::FINALIZING], true)) {
            throw new \RuntimeException('Boiler Finalize requires boiler_status drafted or finalizing.');
        }

        $session->update([
            'boiler_status' => BoilerStatus::FINALIZING,
            'boiler_error' => null,
        ]);

        try {
            $context = $contextBuilder->build($this->story, $this->sessionNumber);
            $result = (new BoilerFinalizeAgent)->prompt(
                view('ai.agents.adaptation.boiler-finalize.prompt', [
                    'context' => $context,
                ])->render()
            )->toArray();

            $events = $eventLoader->forSession($this->story, $this->sessionNumber);
            $validator->validate($result, $session, $events);
            $normalizer->normalize($session, $result);
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
            'boiler_error' => mb_substr($throwable?->getMessage() ?? 'Boiler Finalize failed.', 0, 2_000),
            'session_status' => SessionAdaptationStatusEnum::FAILED,
        ]);
    }
}
