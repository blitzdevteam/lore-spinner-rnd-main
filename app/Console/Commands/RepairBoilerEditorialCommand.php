<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Adaptation\BoilerMomentRangeResolver;
use App\Ai\Adaptation\ConsequenceMapCanonicalizer;
use App\Ai\Adaptation\ConsequenceMapValidator;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Jobs\Adaptation\AdaptationStatusReconciliationJob;
use App\Jobs\Adaptation\BoilerFinalizeJob;
use App\Jobs\Adaptation\ChoiceDesignJob;
use App\Jobs\Adaptation\ConsequenceMappingJob;
use App\Jobs\Adaptation\EditorialVerificationJob;
use App\Jobs\Adaptation\RuntimeNarratorAssemblyJob;
use App\Jobs\Adaptation\SessionArchitectureJob;
use App\Jobs\Adaptation\SessionCloseJob;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Services\Adaptation\SessionEventLoader;
use App\Support\Adaptation\SessionAdaptationChain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RepairBoilerEditorialCommand extends Command
{
    protected $signature = 'stories:repair-boiler-editorial
                            {story : Story ID or slug}
                            {--session= : Session number to repair}
                            {--from=consequence : Earliest phase to rerun: draft, architecture, choice, consequence, or finalize}';

    protected $description = 'Repair a failed Boiler editorial audit from the affected authoritative phase';

    public function handle(
        ConsequenceMapCanonicalizer $canonicalizer,
        ConsequenceMapValidator $validator,
    ): int {
        $story = $this->resolveStory();
        if ($story?->adaptation === null || ! $story->adaptation->boiler_enabled) {
            $this->error('Boiler-enabled story adaptation not found.');

            return self::FAILURE;
        }

        $sessionNumber = filter_var($this->option('session'), FILTER_VALIDATE_INT);
        if ($sessionNumber === false || $sessionNumber < 1) {
            $this->error('Provide a positive --session number.');

            return self::FAILURE;
        }

        $from = mb_strtolower(mb_trim((string) $this->option('from')));
        if (! in_array($from, ['draft', 'architecture', 'choice', 'consequence', 'finalize'], true)) {
            $this->error('--from must be draft, architecture, choice, consequence, or finalize.');

            return self::FAILURE;
        }

        $session = $story->adaptation->sessionAdaptations()
            ->where('session_number', $sessionNumber)
            ->first();
        if ($session === null) {
            $this->error("Session {$sessionNumber} does not exist for this adaptation.");

            return self::FAILURE;
        }

        // A session that failed at Phase 3 is terminally FAILED: BoilerDraftJob rethrows
        // the stored error on every redispatch. Rerunning from draft is the only way back
        // once the underlying defect (usually unsatisfiable Session Map guidance) is fixed.
        if ($from === 'draft') {
            return $this->rerunFromDraft($story, $session, $sessionNumber);
        }

        if (empty($session->boiler_draft)) {
            $this->error(
                "Session {$sessionNumber} does not have a validated Boiler Draft. Use --from=draft to rebuild it."
            );

            return self::FAILURE;
        }

        if ($from === 'consequence' && empty($session->session_choice_design)) {
            $this->error("Session {$sessionNumber} does not have Phase 5 Choice Design.");

            return self::FAILURE;
        }
        if (
            $from === 'finalize'
            && (
                empty($session->session_choice_design)
                || empty($session->choice_consequence_map)
                || empty($session->session_close_design)
            )
        ) {
            $this->error("Session {$sessionNumber} does not have all validated Phase 5-7 artifacts.");

            return self::FAILURE;
        }

        $canonicalConsequenceMap = null;
        $regenerateConsequenceMap = false;
        if ($from === 'consequence') {
            /** @var array<string, mixed> $existingConsequenceMap */
            $existingConsequenceMap = (array) $session->choice_consequence_map;
            /** @var array<string, mixed> $choiceDesign */
            $choiceDesign = (array) $session->session_choice_design;
            /** @var array<string, mixed> $draft */
            $draft = (array) $session->boiler_draft;
            $canonicalConsequenceMap = $canonicalizer->canonicalize(
                $existingConsequenceMap,
                $choiceDesign,
            );

            try {
                $validator->validate(
                    $canonicalConsequenceMap,
                    $choiceDesign,
                    $draft,
                );
            } catch (RuntimeException) {
                $canonicalConsequenceMap = null;
                $regenerateConsequenceMap = true;
            }
        }

        DB::transaction(function () use ($story, $session, $from, $canonicalConsequenceMap): void {
            $story->adaptation->update([
                'adaptation_status' => AdaptationStatusEnum::ADAPTING_SESSIONS,
            ]);

            if (in_array($from, ['architecture', 'choice', 'finalize'], true)) {
                $session->playableCut()->delete();
                $updates = [
                    'boiler_status' => BoilerStatus::DRAFTED,
                    'boiler_error' => null,
                    'boiler_final_cut' => null,
                    'boiler_finalized_at' => null,
                    'editorial_verification' => null,
                    'runtime_narrator_prompt' => null,
                    'runtime_narrator_assembled_at' => null,
                ];
                if (in_array($from, ['architecture', 'choice'], true)) {
                    $updates['session_choice_design'] = null;
                    $updates['choice_consequence_map'] = null;
                    $updates['session_close_design'] = null;
                }
                if ($from === 'architecture') {
                    $updates['session_architecture'] = null;
                }
                $session->update($updates);

                return;
            }

            // Reset boiler_status to DRAFTED so any orphaned finalize job still
            // lingering in the queue fails the "drafted or finalizing" guard
            // in BoilerFinalizeJob rather than running a second concurrent time.
            $session->playableCut()->delete();
            $session->update([
                'boiler_error' => null,
                'boiler_status' => BoilerStatus::DRAFTED,
                'boiler_final_cut' => null,
                'boiler_finalized_at' => null,
                'choice_consequence_map' => $canonicalConsequenceMap,
                'editorial_verification' => null,
                'runtime_narrator_prompt' => null,
                'runtime_narrator_assembled_at' => null,
            ]);
        });

        $jobs = match ($from) {
            'architecture' => [
                new SessionArchitectureJob($story, $sessionNumber),
                new ChoiceDesignJob($story, $sessionNumber),
                new ConsequenceMappingJob($story, $sessionNumber),
                new SessionCloseJob($story, $sessionNumber),
                new BoilerFinalizeJob($story, $sessionNumber),
                new EditorialVerificationJob($story, $sessionNumber),
                new RuntimeNarratorAssemblyJob($story, $sessionNumber),
                new AdaptationStatusReconciliationJob($story),
            ],
            'choice' => [
                new ChoiceDesignJob($story, $sessionNumber),
                new ConsequenceMappingJob($story, $sessionNumber),
                new SessionCloseJob($story, $sessionNumber),
                new BoilerFinalizeJob($story, $sessionNumber),
                new EditorialVerificationJob($story, $sessionNumber),
                new RuntimeNarratorAssemblyJob($story, $sessionNumber),
                new AdaptationStatusReconciliationJob($story),
            ],
            'finalize' => [
                new BoilerFinalizeJob($story, $sessionNumber),
                new EditorialVerificationJob($story, $sessionNumber),
                new RuntimeNarratorAssemblyJob($story, $sessionNumber),
                new AdaptationStatusReconciliationJob($story),
            ],
            default => [
                ...($regenerateConsequenceMap
                    ? [new ConsequenceMappingJob($story, $sessionNumber)]
                    : []),
                // This path resets boiler_status to DRAFTED and drops the final cut,
                // so Finalize has to rebuild it before Editorial has anything to audit.
                new BoilerFinalizeJob($story, $sessionNumber),
                new EditorialVerificationJob($story, $sessionNumber),
                new RuntimeNarratorAssemblyJob($story, $sessionNumber),
                new AdaptationStatusReconciliationJob($story),
            ],
        };

        Bus::chain($jobs)->onQueue('adaptation')->dispatch();

        $strategy = $from === 'consequence' && ! $regenerateConsequenceMap
            ? 'canonicalized existing Consequence Map'
            : "rerun from {$from}";
        $this->info("Dispatched Session {$sessionNumber} Boiler repair: {$strategy}.");

        return self::SUCCESS;
    }

    private function rerunFromDraft(Story $story, SessionAdaptation $session, int $sessionNumber): int
    {
        $guidance = collect((array) data_get($story->adaptation->story_session_map, 'boiling_profile.per_session_guidance', []))
            ->firstWhere('session_number', $sessionNumber);

        // Pre-entry events cannot become moments, so the playable count — not the raw
        // allocation — is what the Draft has to work with. Redispatching into guidance
        // the validator can never satisfy just burns model calls and lands back on
        // FAILED, so refuse it with the actual numbers.
        $startPosition = isset($session->entry_point_diagnosis['start_event_position'])
            ? (int) $session->entry_point_diagnosis['start_event_position']
            : null;
        $events = app(SessionEventLoader::class)->forSession($story, $sessionNumber, $startPosition);
        $range = BoilerMomentRangeResolver::resolve((array) $guidance, $events);

        if ($range['playable_event_count'] < 1) {
            $this->error(sprintf(
                'Session %d has no playable events after its entry point, so no Boiler draft can be built. '
                .'Re-run Entry Point diagnosis or regenerate the Story Session Map before rerunning this session.',
                $sessionNumber,
            ));

            return self::FAILURE;
        }

        if ($range['clamped']) {
            $this->warn(sprintf(
                'Session %d Session Map recommends %s moments but only %d events are playable after the entry point. '
                .'The Draft will be asked for %d-%d instead.',
                $sessionNumber,
                data_get($guidance, 'recommended_moment_count_min', '?').'-'.data_get($guidance, 'recommended_moment_count_max', '?'),
                $range['playable_event_count'],
                $range['min'],
                $range['max'],
            ));
        }

        DB::transaction(function () use ($story, $session): void {
            $story->adaptation->update([
                'adaptation_status' => AdaptationStatusEnum::ADAPTING_SESSIONS,
            ]);
            $session->playableCut()->delete();
            $session->update([
                'session_status' => SessionAdaptationStatusEnum::PENDING,
                'boiler_status' => BoilerStatus::PENDING,
                'boiler_error' => null,
                'boiler_draft' => null,
                'boiler_drafting_started_at' => null,
                'boiler_generated_at' => null,
                'boiler_final_cut' => null,
                'boiler_finalized_at' => null,
                'session_architecture' => null,
                'session_choice_design' => null,
                'choice_consequence_map' => null,
                'session_close_design' => null,
                'editorial_verification' => null,
                'runtime_narrator_prompt' => null,
                'runtime_narrator_assembled_at' => null,
            ]);
        });

        Bus::chain([
            ...SessionAdaptationChain::jobsForSession($story, $sessionNumber),
            new AdaptationStatusReconciliationJob($story),
        ])->onQueue('adaptation')->dispatch();

        $this->info("Dispatched Session {$sessionNumber} Boiler repair: full rerun from draft.");

        return self::SUCCESS;
    }

    private function resolveStory(): ?Story
    {
        $identifier = (string) $this->argument('story');

        return is_numeric($identifier)
            ? Story::find((int) $identifier)
            : Story::where('slug', $identifier)->first();
    }
}
