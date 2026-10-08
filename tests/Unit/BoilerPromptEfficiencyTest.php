<?php

declare(strict_types=1);

use App\Ai\Adaptation\StorySessionMapSlice;
use App\Ai\Agents\Adaptation\BoilerDraftAgent;
use App\Ai\Agents\Adaptation\BoilerFinalizeAgent;
use App\Ai\Agents\Adaptation\ChoiceDesignAgent;
use App\Ai\Agents\Adaptation\ConsequenceMappingAgent;
use App\Ai\Agents\Adaptation\EditorialVerificationAgent;
use App\Ai\Agents\Adaptation\EntryPointDiagnosisAgent;
use App\Ai\Agents\Adaptation\FormatDetectionAgent;
use App\Ai\Agents\Adaptation\IpAuditAgent;
use App\Ai\Agents\Adaptation\IpTrimmingMergeAgent;
use App\Ai\Agents\Adaptation\SessionArchitectureAgent;
use App\Ai\Agents\Adaptation\SessionCloseAgent;
use App\Ai\Agents\Adaptation\StorySessionMapAgent;
use App\Ai\Agents\Adaptation\VoiceLockBookAgent;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;

it('slices the story map to one session and its neighbours, keeping story-wide sections', function () {
    $map = [
        'session_allocation' => array_map(fn (int $n): array => ['session_number' => $n], range(1, 6)),
        'arc_progression' => array_map(fn (int $n): array => ['session_number' => $n], range(1, 6)),
        'branch_opportunities' => [
            ['session_number' => 3, 'downstream_payoff_session' => 5],
            ['session_number' => 1, 'downstream_payoff_session' => 3],
            ['session_number' => 5, 'downstream_payoff_session' => 6],
        ],
        'cross_session_payoff_plan' => [
            ['choice_reference' => 'Session 3, event 40', 'echo_session' => 4, 'payoff_session' => 6],
            ['choice_reference' => 'Session 1, event 9', 'echo_session' => 2, 'payoff_session' => 2],
        ],
        'boiling_profile' => ['compaction_level' => 'balanced', 'per_session_guidance' => array_map(fn (int $n): array => ['session_number' => $n], range(1, 6))],
        'branch_dimensions' => [['dimension_name' => 'trust']],
        'persistent_state_schema' => ['npcs' => []],
    ];

    $slice = StorySessionMapSlice::forSession($map, 3, ['persistent_state_schema']);

    expect(array_column($slice['session_allocation'], 'session_number'))->toBe([2, 3, 4])
        ->and(array_column($slice['arc_progression'], 'session_number'))->toBe([2, 3, 4])
        ->and($slice['branch_opportunities'])->toHaveCount(2)
        ->and($slice['cross_session_payoff_plan'])->toHaveCount(1)
        ->and(array_column($slice['boiling_profile']['per_session_guidance'], 'session_number'))->toBe([3])
        ->and($slice['boiling_profile']['compaction_level'])->toBe('balanced')
        ->and($slice['branch_dimensions'])->toBe([['dimension_name' => 'trust']])
        ->and($slice)->not->toHaveKey('persistent_state_schema');
});

it('runs editorial verification on GPT-6 Luna with explicit reasoning and no sampling parameters', function () {
    $reflection = new ReflectionClass(EditorialVerificationAgent::class);

    expect($reflection->getAttributes(Model::class)[0]->getArguments()[0])->toBe('gpt-6-luna')
        ->and($reflection->getAttributes(Temperature::class))->toBe([])
        ->and($reflection->newInstanceWithoutConstructor()->providerOptions('openai'))->toHaveKey('reasoning.effort');
});

it('runs each phase on the model and effort sized to its task, with no sampling parameters', function (string $agent, string $model, string $effort) {
    $reflection = new ReflectionClass($agent);

    expect($reflection->getAttributes(Model::class)[0]->getArguments()[0])->toBe($model)
        ->and($reflection->getAttributes(Temperature::class))->toBe([])
        ->and($reflection->newInstanceWithoutConstructor()->providerOptions('openai'))->toBe(['reasoning' => ['effort' => $effort]]);
})->with([
    [FormatDetectionAgent::class, 'gpt-6-luna', 'low'],
    [IpTrimmingMergeAgent::class, 'gpt-6.1-sol', 'low'],
    [IpAuditAgent::class, 'gpt-6.1-sol', 'low'],
    [StorySessionMapAgent::class, 'gpt-6.1-sol', 'medium'],
    [EntryPointDiagnosisAgent::class, 'gpt-6.1-sol', 'medium'],
    [SessionArchitectureAgent::class, 'gpt-6.1-sol', 'low'],
    [ChoiceDesignAgent::class, 'gpt-6.1-sol', 'medium'],
    [ConsequenceMappingAgent::class, 'gpt-6.1-sol', 'low'],
    [SessionCloseAgent::class, 'gpt-6.1-sol', 'medium'],
]);

it('stops repairing cleanly when another attempt cannot finish inside the job time limit', function () {
    $job = new class
    {
        use App\Support\Adaptation\GeneratesValidatedArtifact;

        public int $timeout = 45;

        public int $calls = 0;

        public function run(): array
        {
            return $this->generateValidatedArtifact(
                artifact: 'test',
                originalPrompt: 'p',
                generate: function (): array {
                    $this->calls++;
                    usleep(40_000);

                    return [];
                },
                validate: fn () => throw new RuntimeException('still invalid'),
                repairPrompt: fn (): string => 'p',
            );
        }
    };

    expect(fn () => $job->run())->toThrow(App\Exceptions\Adaptation\ArtifactValidationException::class, 'still invalid')
        ->and($job->calls)->toBe(1);
});

it('enables Anthropic prompt caching on the Opus phases', function (string $agent) {
    $instance = (new ReflectionClass($agent))->newInstanceWithoutConstructor();

    expect($instance->providerOptions('anthropic'))->toBe(['cache_control' => ['type' => 'ephemeral']]);
})->with([BoilerDraftAgent::class, BoilerFinalizeAgent::class, VoiceLockBookAgent::class]);

it('starts a repair prompt with the unchanged original prompt and never HTML-escapes it', function () {
    $original = 'TASK: keep "quotes" & apostrophes\' intact.';

    $prompt = view('ai.agents.adaptation.boiler-draft.repair-prompt', [
        'originalPrompt' => $original,
        'validationError' => 'Moment S1_M02 goes back in the story.',
        'previousDraft' => ['note' => 'He said "no".'],
    ])->render();

    expect($prompt)->toStartWith($original)
        ->and($prompt)->not->toContain('&quot;')
        ->and($prompt)->not->toContain('&#039;')
        ->and($prompt)->toContain('He said \"no\".');
});
