<?php

declare(strict_types=1);

namespace App\Support\Adaptation;

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
use Illuminate\Support\Facades\Bus;

final class SessionAdaptationChain
{
    /**
     * @return array<int, object>
     */
    public static function jobsForSession(Story $story, int $sessionNumber): array
    {
        $boilerEnabled = (bool) $story->adaptation()->value('boiler_enabled');

        $jobs = [
            new EntryPointDiagnosisJob($story, $sessionNumber),
        ];

        if ($boilerEnabled) {
            $jobs[] = new BoilerDraftJob($story, $sessionNumber);
        }

        array_push(
            $jobs,
            new SessionArchitectureJob($story, $sessionNumber),
            new ChoiceDesignJob($story, $sessionNumber),
            new ConsequenceMappingJob($story, $sessionNumber),
            new SessionCloseJob($story, $sessionNumber),
        );

        if ($boilerEnabled) {
            $jobs[] = new BoilerFinalizeJob($story, $sessionNumber);
        }

        array_push(
            $jobs,
            new EditorialVerificationJob($story, $sessionNumber),
            new RuntimeNarratorAssemblyJob($story, $sessionNumber),
        );

        return $jobs;
    }

    /**
     * @param  array<int, int>  $sessionNumbers
     */
    public static function dispatchBatch(Story $story, array $sessionNumbers, ?callable $finally = null): void
    {
        $sessionNumbers = array_values(array_unique(array_map('intval', $sessionNumbers)));

        if ($sessionNumbers === []) {
            return;
        }

        $storyId = $story->id;
        $sessionChains = [];

        foreach ($sessionNumbers as $sessionNumber) {
            $sessionChains[] = self::jobsForSession($story, $sessionNumber);
        }

        // Sessions are independent chains. Without allowFailures() the first failing
        // job cancels the whole batch and stamps finished_at, which fires the finally
        // callback while the other sessions are still mid-chain and leaves them
        // running against a batch that has already been declared over.
        $batch = Bus::batch($sessionChains)
            ->allowFailures()
            ->onQueue('adaptation');

        if ($finally !== null) {
            $batch->finally($finally);
        }

        $batch->dispatch();
    }
}
