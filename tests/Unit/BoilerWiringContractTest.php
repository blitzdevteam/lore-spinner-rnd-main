<?php

declare(strict_types=1);

it('keeps one canonical session-chain definition in the approved order', function () {
    $chain = file_get_contents(app_path('Support/Adaptation/SessionAdaptationChain.php'));
    $storyMapJob = file_get_contents(app_path('Jobs/Adaptation/StorySessionMapJob.php'));

    $orderedClasses = [
        'EntryPointDiagnosisJob',
        'BoilerDraftJob',
        'SessionArchitectureJob',
        'ChoiceDesignJob',
        'ConsequenceMappingJob',
        'SessionCloseJob',
        'BoilerFinalizeJob',
        'EditorialVerificationJob',
        'RuntimeNarratorAssemblyJob',
    ];

    $lastPosition = -1;
    foreach ($orderedClasses as $class) {
        $position = mb_strpos($chain, "new {$class}");
        expect($position)->not->toBeFalse();
        expect((int) $position)->toBeGreaterThan($lastPosition);
        $lastPosition = (int) $position;
    }

    expect($storyMapJob)
        ->toContain('SessionAdaptationChain::dispatchBatch')
        ->not->toContain('Bus::batch(');
});

it('keeps Boiler schema additive and game pointers null-on-delete', function () {
    $gameMigration = file_get_contents(database_path('migrations/2026_07_19_000005_add_boiler_runtime_columns_to_games_table.php'));
    $promptMigration = file_get_contents(database_path('migrations/2026_07_19_000006_add_source_prompt_id_to_prompts_table.php'));

    expect($gameMigration)
        ->toContain('current_playable_session_cut_id')
        ->toContain('current_playable_moment_id')
        ->toContain('->nullOnDelete()')
        ->and($promptMigration)
        ->toContain("ulid('source_prompt_id')")
        ->toContain("unique(['game_id', 'source_prompt_id'])");
});

it('transfers completed status ownership from Editorial to Assembly', function () {
    $editorial = file_get_contents(app_path('Jobs/Adaptation/EditorialVerificationJob.php'));
    $assembly = file_get_contents(app_path('Jobs/Adaptation/RuntimeNarratorAssemblyJob.php'));

    expect($editorial)->not->toContain("'session_status' => SessionAdaptationStatusEnum::COMPLETED")
        ->and($assembly)
        ->toContain("'session_status'                => SessionAdaptationStatusEnum::COMPLETED")
        ->toContain('RuntimeNarratorPromptValidator')
        ->toContain('function failed(');
});

it('keeps moment order as resolver routing authority', function () {
    $resolver = file_get_contents(app_path('Services/Runtime/PlayableMomentResolver.php'));

    expect($resolver)
        ->toContain("'moment_order', \$currentMoment->moment_order + 1")
        ->not->toContain("where('moment_key', \$currentMoment->scene_card['next_moment_key']");
});

it('uses the explicit frontend Boiler discriminator for Continue behavior', function () {
    $card = file_get_contents(resource_path('js/components/GameplayChatCard.vue'));
    $resource = file_get_contents(app_path('Http/Resources/GameResource.php'));

    expect($resource)->toContain("'uses_playable_moments'")
        ->and($card)
        ->toContain('usesPlayableMoments')
        ->toContain('!props.usesPlayableMoments && canInteract.value');
});

it('runs the shared game reset only on a successful reconciliation transition', function () {
    $reconciliation = file_get_contents(app_path('Jobs/Adaptation/AdaptationStatusReconciliationJob.php'));
    $controller = file_get_contents(app_path('Http/Controllers/User/GameController.php'));

    expect($reconciliation)
        ->toContain('if ($status !== AdaptationStatusEnum::COMPLETED)')
        ->toContain('$gameResetService->reset($game)')
        ->toContain('$lockedAdaptation->adaptation_status === AdaptationStatusEnum::COMPLETED')
        ->and($controller)
        ->toContain('$this->gameResetService->reset($game)');
});

it('runs every validated phase through one shared generate-validate-repair loop', function () {
    $loop = file_get_contents(app_path('Support/Adaptation/GeneratesValidatedArtifact.php'));

    // The loop owns the attempt budget, the log-event names, and the terminal
    // failure mode so no phase can drift from the others.
    expect($loop)
        ->toContain('MAX_VALIDATION_ATTEMPTS = 3')
        ->toContain('boiler.{$artifact}_validation_failed')
        ->toContain('boiler.{$artifact}_corrected')
        ->toContain('ArtifactValidationException');

    $phases = [
        'BoilerDraftJob' => 'draft',
        'BoilerFinalizeJob' => 'finalize',
        'SessionArchitectureJob' => 'architecture',
        'ChoiceDesignJob' => 'choice_design',
        'ConsequenceMappingJob' => 'consequence_mapping',
    ];

    foreach ($phases as $class => $artifact) {
        $job = file_get_contents(app_path("Jobs/Adaptation/{$class}.php"));

        expect($job)
            ->toContain('use App\Support\Adaptation\GeneratesValidatedArtifact;')
            ->toContain("artifact: '{$artifact}'")
            // A semantic failure must not be replayed by the queue.
            ->toContain('$this->failTerminally($throwable)')
            // A cancelled batch must not keep spending model calls.
            ->toContain('if ($this->batch()?->cancelled())');
    }
});

it('repairs invalid Boiler Draft references with validation feedback', function () {
    $job = file_get_contents(app_path('Jobs/Adaptation/BoilerDraftJob.php'));
    $agent = file_get_contents(app_path('Ai/Agents/Adaptation/BoilerDraftAgent.php'));
    $systemPrompt = file_get_contents(resource_path('views/ai/agents/adaptation/boiler-draft/system-prompt.blade.php'));
    $repairPrompt = file_get_contents(resource_path('views/ai/agents/adaptation/boiler-draft/repair-prompt.blade.php'));

    expect($job)
        ->toContain('boiler-draft.repair-prompt')
        ->toContain("artifact: 'draft'")
        ->and($agent)
        ->toContain('each event ID may occur in at most one moment_drafts source_event_ids list')
        ->toContain('Copy one complete moment_drafts[].moment_key exactly')
        ->and($systemPrompt)
        ->toContain('strict partition')
        ->toContain('must copy one complete `moment_drafts[].moment_key` exactly')
        ->and($repairPrompt)
        ->toContain('VALIDATION ERROR:')
        ->toContain('PREVIOUS INVALID DRAFT:')
        ->toContain('Return only the complete corrected structured output');
});

it('keeps long adaptation jobs inside the database queue lease', function () {
    $queueConfig = file_get_contents(config_path('queue.php'));
    $draftJob = file_get_contents(app_path('Jobs/Adaptation/BoilerDraftJob.php'));
    $finalizeJob = file_get_contents(app_path('Jobs/Adaptation/BoilerFinalizeJob.php'));
    $architectureJob = file_get_contents(app_path('Jobs/Adaptation/SessionArchitectureJob.php'));

    expect($queueConfig)
        ->toContain("env('DB_QUEUE_RETRY_AFTER', 1200)")
        ->and($draftJob)
        ->toContain('is already failed.')
        ->and($finalizeJob)
        ->toContain('boiler.finalize_not_claimed')
        ->and($architectureJob)
        ->toContain('$session->boiler_status !== BoilerStatus::DRAFTED')
        ->toContain('requires a validated Boiler Draft');
});

it('canonicalizes Finalize references and repairs remaining invalid scene-card fields', function () {
    $job = file_get_contents(app_path('Jobs/Adaptation/BoilerFinalizeJob.php'));
    $canonicalizer = file_get_contents(app_path('Ai/Adaptation/BoilerFinalCutCanonicalizer.php'));
    $validator = file_get_contents(app_path('Ai/Adaptation/BoilerFinalCutValidator.php'));
    $repairPrompt = file_get_contents(resource_path('views/ai/agents/adaptation/boiler-finalize/repair-prompt.blade.php'));

    expect($job)
        ->toContain('BoilerFinalCutCanonicalizer')
        ->toContain('boiler-finalize.repair-prompt')
        ->toContain("artifact: 'finalize'")
        ->and($canonicalizer)
        ->toContain("\$card['choice_ids'] = \$choiceIdsByMoment->get(\$momentKey, [])")
        ->toContain("\$card['next_moment_key']")
        ->toContain('finalBridgeFallback')
        ->toContain("'hook_transition', 'next_bridge_guidance'")
        ->toContain("\$finalCut['event_role_map']")
        ->and($validator)
        ->toContain('must contain exactly the choices authored for that moment')
        ->and($repairPrompt)
        ->toContain('PREVIOUS CANONICALIZED BUT INVALID CUT:')
        ->toContain('Return only the complete corrected structured output');
});

it('records a Boiler editorial audit as advisory without stopping the chain', function () {
    $job = file_get_contents(app_path('Jobs/Adaptation/EditorialVerificationJob.php'));
    $command = file_get_contents(app_path('Console/Commands/InspectBoilerCommand.php'));

    expect($job)
        ->toContain('public int $tries = 1')
        ->toContain('public int $timeout = 600')
        ->toContain("\$session->update(['editorial_verification' => \$verification])")
        ->toContain('boiler.editorial_verification_advisory')
        ->toContain("'revision_instructions' => \$revisions")
        ->toContain('REVIEW_PREFIX')
        // The audit grades what BoilerFinalCutValidator already proved, so a REVISE
        // verdict must never throw. Only genuinely broken inputs stop the chain.
        ->not->toContain('throw new RuntimeException($message)')
        ->not->toContain('boilerFailureMessage')
        ->and($command)
        ->toContain('Editorial Boiler audit:')
        ->toContain('Revision: ')
        ->toContain("\$result['verdict'] ?? null) === 'REVISE'");
});

it('still hard-fails Boiler editorial when its inputs are genuinely missing', function () {
    $job = file_get_contents(app_path('Jobs/Adaptation/EditorialVerificationJob.php'));

    // Advisory grading must not soften the pre-flight contract: without a voice profile
    // or a finalized cut the audit has nothing to grade and reports the absence as an
    // editorial defect, which would bury the real cause.
    expect($job)
        ->toContain('voice_profile missing')
        ->toContain('expected finalized')
        ->toContain('boiler_final_cut is empty')
        ->toContain('normalized playable cut is missing')
        ->toContain('Boiler Finalize must complete first.');
});

it('repairs Architecture pacing and canonicalizes Phase 6 choice authority before Editorial', function () {
    $architectureJob = file_get_contents(app_path('Jobs/Adaptation/SessionArchitectureJob.php'));
    $architectureCanonicalizer = file_get_contents(app_path('Ai/Adaptation/SessionArchitectureCanonicalizer.php'));
    $architectureValidator = file_get_contents(app_path('Ai/Adaptation/SessionArchitectureValidator.php'));
    $choiceJob = file_get_contents(app_path('Jobs/Adaptation/ChoiceDesignJob.php'));
    $choiceCanonicalizer = file_get_contents(app_path('Ai/Adaptation/ChoiceDesignCanonicalizer.php'));
    $consequenceJob = file_get_contents(app_path('Jobs/Adaptation/ConsequenceMappingJob.php'));
    $architecturePrompt = file_get_contents(resource_path('views/ai/agents/adaptation/session-architecture/repair-prompt.blade.php'));
    $choicePrompt = file_get_contents(resource_path('views/ai/agents/adaptation/choice-design/repair-prompt.blade.php'));
    $consequencePrompt = file_get_contents(resource_path('views/ai/agents/adaptation/consequence-mapping/repair-prompt.blade.php'));

    expect($architectureJob)
        ->toContain('SessionArchitectureCanonicalizer')
        ->toContain('SessionArchitectureValidator')
        ->toContain("artifact: 'architecture'")
        ->toContain('session-architecture.repair-prompt')
        ->and($architecturePrompt)
        ->toContain('Exactly four rows use a')
        ->and($architectureCanonicalizer)
        ->toContain('Session-end hook authority')
        ->and($architectureValidator)
        ->toContain('The final Draft moment must own a BRANCHING Architecture slot')
        ->and($choiceJob)
        ->toContain('ChoiceDesignCanonicalizer')
        ->toContain("artifact: 'choice_design'")
        ->toContain('choice-design.repair-prompt')
        ->and($choicePrompt)
        ->toContain('Draft CHOICE-role rows are candidate pressures')
        ->and($choiceCanonicalizer)
        ->toContain("'category'] ?? null) === 'SESSION_END_HOOK'")
        ->toContain("\$choices[\$sessionEndIndex]['moment_key'] = \$finalMomentKey")
        ->and($consequenceJob)
        ->toContain('ConsequenceMapCanonicalizer')
        ->toContain('ConsequenceMapValidator')
        ->toContain("artifact: 'consequence_mapping'")
        ->toContain('consequence-mapping.repair-prompt')
        ->and($consequencePrompt)
        ->toContain('Phase 5 Choice Design is the sole authority');
});

it('provides scoped commands for replaying only failed Editorial dependencies', function () {
    $command = file_get_contents(app_path('Console/Commands/RepairBoilerEditorialCommand.php'));

    expect($command)
        ->toContain('stories:repair-boiler-editorial')
        ->toContain('ConsequenceMapCanonicalizer')
        ->toContain('ConsequenceMapValidator')
        ->toContain("'choice'")
        ->toContain("'finalize'")
        ->toContain("'boiler_status' => BoilerStatus::DRAFTED")
        ->toContain('new SessionArchitectureJob')
        ->toContain('new ConsequenceMappingJob')
        ->toContain('new EditorialVerificationJob')
        ->toContain('new RuntimeNarratorAssemblyJob')
        ->toContain('new AdaptationStatusReconciliationJob');
});

it('allowlists MONTAGE in Editorial Boiler audit and fills Draft source IDs before validate', function () {
    $editorialSystem = file_get_contents(resource_path('views/ai/agents/adaptation/editorial-verification/system-prompt.blade.php'));
    $editorialPrompt = file_get_contents(resource_path('views/ai/agents/adaptation/editorial-verification/prompt.blade.php'));
    $draftSystem = file_get_contents(resource_path('views/ai/agents/adaptation/boiler-draft/system-prompt.blade.php'));
    $draftRepair = file_get_contents(resource_path('views/ai/agents/adaptation/boiler-draft/repair-prompt.blade.php'));
    $draftJob = file_get_contents(app_path('Jobs/Adaptation/BoilerDraftJob.php'));

    expect($editorialSystem)
        ->toContain('ALLOWED EVENT ROLE VOCABULARY')
        ->toContain('MONTAGE')
        ->toContain('Never REVISE solely because a role is MONTAGE')
        ->and($editorialPrompt)
        ->toContain('Never ban MONTAGE')
        ->and($draftSystem)
        ->toContain('non-empty `source_event_ids`')
        ->and($draftRepair)
        ->toContain('fill each empty moment from the events whose `event_role_map[]')
        ->and($draftJob)
        ->toContain('BoilerDraftCanonicalizer')
        ->toContain('$canonicalizer->canonicalize');
});
