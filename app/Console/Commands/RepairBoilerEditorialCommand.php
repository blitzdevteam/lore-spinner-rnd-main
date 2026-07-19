<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Adaptation\ConsequenceMapCanonicalizer;
use App\Ai\Adaptation\ConsequenceMapValidator;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Jobs\Adaptation\AdaptationStatusReconciliationJob;
use App\Jobs\Adaptation\BoilerFinalizeJob;
use App\Jobs\Adaptation\ChoiceDesignJob;
use App\Jobs\Adaptation\ConsequenceMappingJob;
use App\Jobs\Adaptation\EditorialVerificationJob;
use App\Jobs\Adaptation\RuntimeNarratorAssemblyJob;
use App\Jobs\Adaptation\SessionArchitectureJob;
use App\Jobs\Adaptation\SessionCloseJob;
use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RepairBoilerEditorialCommand extends Command
{
    protected $signature = 'stories:repair-boiler-editorial
                            {story : Story ID or slug}
                            {--session= : Session number to repair}
                            {--from=consequence : Earliest phase to rerun: architecture, choice, or consequence}';

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
        if (! in_array($from, ['architecture', 'choice', 'consequence'], true)) {
            $this->error('--from must be architecture, choice, or consequence.');

            return self::FAILURE;
        }

        $session = $story->adaptation->sessionAdaptations()
            ->where('session_number', $sessionNumber)
            ->first();
        if ($session === null || empty($session->boiler_draft)) {
            $this->error("Session {$sessionNumber} does not have a validated Boiler Draft.");

            return self::FAILURE;
        }

        if ($from === 'consequence' && empty($session->session_choice_design)) {
            $this->error("Session {$sessionNumber} does not have Phase 5 Choice Design.");

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

            if (in_array($from, ['architecture', 'choice'], true)) {
                $session->playableCut()->delete();
                $updates = [
                    'boiler_status' => BoilerStatus::DRAFTED,
                    'boiler_error' => null,
                    'session_choice_design' => null,
                    'choice_consequence_map' => null,
                    'session_close_design' => null,
                    'boiler_final_cut' => null,
                    'boiler_finalized_at' => null,
                    'editorial_verification' => null,
                    'runtime_narrator_prompt' => null,
                    'runtime_narrator_assembled_at' => null,
                ];
                if ($from === 'architecture') {
                    $updates['session_architecture'] = null;
                }
                $session->update($updates);

                return;
            }

            $session->update([
                'boiler_error' => null,
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
            default => [
                ...($regenerateConsequenceMap
                    ? [new ConsequenceMappingJob($story, $sessionNumber)]
                    : []),
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

    private function resolveStory(): ?Story
    {
        $identifier = (string) $this->argument('story');

        return is_numeric($identifier)
            ? Story::find((int) $identifier)
            : Story::where('slug', $identifier)->first();
    }
}
