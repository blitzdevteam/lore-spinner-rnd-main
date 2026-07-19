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
        $position = strpos($chain, "new {$class}");
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
        ->toContain("current_playable_session_cut_id")
        ->toContain("current_playable_moment_id")
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
