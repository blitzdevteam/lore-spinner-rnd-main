<?php

declare(strict_types=1);

namespace App\Support\Adaptation;

use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Jobs\Adaptation\BoilerDraftJob;
use App\Jobs\Adaptation\BoilerFinalizeJob;
use App\Jobs\Adaptation\ChoiceDesignJob;
use App\Jobs\Adaptation\ConsequenceMappingJob;
use App\Jobs\Adaptation\EditorialVerificationJob;
use App\Jobs\Adaptation\EntryPointDiagnosisJob;
use App\Jobs\Adaptation\RuntimeNarratorAssemblyJob;
use App\Jobs\Adaptation\SessionArchitectureJob;
use App\Jobs\Adaptation\SessionCloseJob;
use App\Models\Story;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

final class SessionAdaptationChain
{
    /**
     * @return array<int, object>
     */
    public static function jobsForSession(Story $story, int $sessionNumber): array
    {
        return [
            new EntryPointDiagnosisJob($story, $sessionNumber),
            ...self::jobsAfterEntryPoint($story, $sessionNumber),
        ];
    }

    /**
     * @return array<int, object>
     */
    public static function jobsAfterEntryPoint(Story $story, int $sessionNumber): array
    {
        $boilerEnabled = (bool) $story->adaptation()->value('boiler_enabled');

        return array_values(array_filter([
            $boilerEnabled ? new BoilerDraftJob($story, $sessionNumber) : null,
            new SessionArchitectureJob($story, $sessionNumber),
            new ChoiceDesignJob($story, $sessionNumber),
            new ConsequenceMappingJob($story, $sessionNumber),
            new SessionCloseJob($story, $sessionNumber),
            $boilerEnabled ? new BoilerFinalizeJob($story, $sessionNumber) : null,
            new EditorialVerificationJob($story, $sessionNumber),
            new RuntimeNarratorAssemblyJob($story, $sessionNumber),
        ]));
    }

    /**
     * Every session's entry point runs first, in parallel. Each session's close
     * and next-session fields then hand off to the next session's real opening
     * instead of guessing it. The remaining chains run in parallel afterwards.
     *
     * @param  array<int, int>  $sessionNumbers
     */
    public static function dispatchBatch(Story $story, array $sessionNumbers, ?callable $finally = null): void
    {
        $sessionNumbers = array_values(array_unique(array_map('intval', $sessionNumbers)));

        if ($sessionNumbers === []) {
            return;
        }

        $storyId = $story->id;

        // Sessions are independent chains. Without allowFailures() the first failing
        // job cancels the whole batch and stamps finished_at, which fires the finally
        // callback while the other sessions are still mid-chain and leaves them
        // running against a batch that has already been declared over.
        Bus::batch(array_map(
            static fn (int $sessionNumber): EntryPointDiagnosisJob => new EntryPointDiagnosisJob($story, $sessionNumber),
            $sessionNumbers,
        ))
            ->allowFailures()
            ->onQueue('adaptation')
            ->finally(static function (Batch $entryBatch) use ($storyId, $sessionNumbers, $finally): void {
                self::dispatchAfterEntryPoints(Story::findOrFail($storyId), $sessionNumbers, $finally, $entryBatch);
            })
            ->dispatch();
    }

    /**
     * @param  array<int, int>  $sessionNumbers
     */
    private static function dispatchAfterEntryPoints(Story $story, array $sessionNumbers, ?callable $finally, Batch $entryBatch): void
    {
        $ready = $story->adaptation->sessionAdaptations()
            ->whereIn('session_number', $sessionNumbers)
            ->whereNotNull('entry_point_diagnosis')
            ->where('session_status', '!=', SessionAdaptationStatusEnum::FAILED)
            ->orderBy('session_number')
            ->pluck('session_number')
            ->all();

        if ($ready === []) {
            if ($finally !== null) {
                $finally($entryBatch);
            }

            return;
        }

        $batch = Bus::batch(array_map(
            static fn (int $sessionNumber): array => self::jobsAfterEntryPoint($story, $sessionNumber),
            $ready,
        ))
            ->allowFailures()
            ->onQueue('adaptation');

        if ($finally !== null) {
            $batch->finally($finally);
        }

        $batch->dispatch();
    }
}
