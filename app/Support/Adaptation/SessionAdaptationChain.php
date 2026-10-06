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
use App\Jobs\Adaptation\StoryRepairJob;
use App\Models\Story;
use Illuminate\Support\Facades\Bus;

final class SessionAdaptationChain
{
    /**
     * The story repair pass checks a session against its neighbours, so inside
     * a session's own chain it only runs when the neighbours are already final:
     * a single-session rerun. A multi-session batch runs it once the whole
     * batch has finished instead (see dispatchBatch).
     *
     * @return array<int, object>
     */
    public static function jobsForSession(Story $story, int $sessionNumber, bool $withStoryRepair = true): array
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

            if ($withStoryRepair) {
                $jobs[] = new StoryRepairJob($story, $sessionNumber);
            }
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

        $withStoryRepair = count($sessionNumbers) === 1;

        foreach ($sessionNumbers as $sessionNumber) {
            $sessionChains[] = self::jobsForSession($story, $sessionNumber, $withStoryRepair);
        }

        // Sessions are independent chains. Without allowFailures() the first failing
        // job cancels the whole batch and stamps finished_at, which fires the finally
        // callback while the other sessions are still mid-chain and leaves them
        // running against a batch that has already been declared over.
        $batch = Bus::batch($sessionChains)
            ->allowFailures()
            ->onQueue('adaptation');

        $boilerEnabled = (bool) $story->adaptation()->value('boiler_enabled');

        if (! $withStoryRepair && $boilerEnabled) {
            $batch->finally(static function () use ($storyId, $sessionNumbers, $finally): void {
                self::dispatchStoryRepairPass($storyId, $sessionNumbers, $finally);
            });
        } elseif ($finally !== null) {
            $batch->finally($finally);
        }

        $batch->dispatch();
    }

    /**
     * Repair every session in story order once all of them are final, rebuild
     * the runtime prompt of any session the pass changed, then hand over to
     * the caller's completion step.
     *
     * @param  array<int, int>  $sessionNumbers
     */
    public static function dispatchStoryRepairPass(int $storyId, array $sessionNumbers, ?callable $finally = null): void
    {
        $story = Story::findOrFail($storyId);
        sort($sessionNumbers);

        $jobs = array_map(
            static fn (int $sessionNumber): StoryRepairJob => new StoryRepairJob($story, $sessionNumber, rebuildRuntimePrompt: true),
            $sessionNumbers,
        );

        if ($finally !== null) {
            $jobs[] = static function () use ($finally): void {
                $finally();
            };
        }

        $chain = Bus::chain($jobs)->onQueue('adaptation');

        if ($finally !== null) {
            $chain->catch(static function () use ($finally): void {
                $finally();
            });
        }

        $chain->dispatch();
    }
}
