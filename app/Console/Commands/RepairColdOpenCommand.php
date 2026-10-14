<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Adaptation\BannedProseValidator;
use App\Ai\Adaptation\ColdOpenRepairContext;
use App\Ai\Adaptation\ColdOpenRepairValidator;
use App\Ai\Adaptation\StoryRepairContext;
use App\Ai\Agents\Adaptation\ColdOpenRepairAgent;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Services\Adaptation\SessionEventLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Rewrite a story's cold opens from its own diagnosis, voice, narrator rules
 * and scene cards, under the same punctuation pass and ban scan as the Boiler.
 *
 * Prints each candidate for review. Pass --apply to store them. The
 * entry-point pipeline agent is unchanged.
 */
final class RepairColdOpenCommand extends Command
{
    private const int MAX_ATTEMPTS = 3;

    protected $signature = 'stories:repair-cold-open
                            {story : Story ID or slug}
                            {--session=1 : Session number}
                            {--all : Repair every session of the story, in order}
                            {--dry-run : Print the pulled facts without calling the model}
                            {--show-prompt : With --dry-run, print the full prompt}
                            {--apply : Store each valid cold open on its session diagnosis}';

    protected $description = 'Repair a story cold open from its diagnosis, voice, narrator rules, and source';

    public function handle(
        ColdOpenRepairContext $contextBuilder,
        ColdOpenRepairValidator $validator,
        BannedProseValidator $bans,
        StoryRepairContext $storyContext,
        SessionEventLoader $eventLoader,
    ): int {
        if ($this->option('dry-run') && $this->option('apply')) {
            $this->error('Choose either --dry-run or --apply.');

            return self::FAILURE;
        }

        $story = $this->resolveStory();

        if ($story?->adaptation === null) {
            $this->error('No matching story with an adaptation found.');

            return self::FAILURE;
        }

        $sessions = $this->sessions($story);

        if ($sessions === null) {
            return self::FAILURE;
        }

        $failed = [];

        foreach ($sessions as $session) {
            $repaired = $this->repairSession($story, $session, $contextBuilder, $validator, $bans, $storyContext, $eventLoader);

            if (! $repaired) {
                $failed[] = (int) $session->session_number;
            }

            $this->newLine();
        }

        if ($failed !== []) {
            $this->error('Not repaired: session '.implode(', ', $failed).'.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, SessionAdaptation>|null
     */
    private function sessions(Story $story): ?Collection
    {
        $query = $story->adaptation->sessionAdaptations()->orderBy('session_number');

        if ($this->option('all')) {
            $sessions = $query->get();

            if ($sessions->isEmpty()) {
                $this->error('This adaptation has no sessions.');

                return null;
            }

            return $sessions;
        }

        $sessionNumber = filter_var($this->option('session'), FILTER_VALIDATE_INT);

        if ($sessionNumber === false || $sessionNumber < 1) {
            $this->error('Provide a positive --session number, or --all.');

            return null;
        }

        $session = $query->where('session_number', $sessionNumber)->first();

        if ($session === null) {
            $this->error("Session {$sessionNumber} does not exist for this adaptation.");

            return null;
        }

        return collect([$session]);
    }

    private function repairSession(
        Story $story,
        SessionAdaptation $session,
        ColdOpenRepairContext $contextBuilder,
        ColdOpenRepairValidator $validator,
        BannedProseValidator $bans,
        StoryRepairContext $storyContext,
        SessionEventLoader $eventLoader,
    ): bool {
        $sessionNumber = (int) $session->session_number;
        $diagnosis = (array) ($session->entry_point_diagnosis ?? []);
        $voiceProfile = (array) ($story->adaptation->voice_profile ?? []);

        $context = $contextBuilder->build(
            $story->title,
            $story->slug,
            $sessionNumber,
            $contextBuilder->protagonistFor($story),
            $voiceProfile,
            $diagnosis,
            $contextBuilder->sourceWindow(
                $this->sourceEvents($story, $sessionNumber, $eventLoader),
                (int) ($diagnosis['start_event_position'] ?? 0),
            ),
            $this->previousSessionClose($story, $sessionNumber, $contextBuilder),
            $storyContext->narratorRules((string) $session->runtime_narrator_prompt),
            (array) ($storyContext->moments($session)->first()?->scene_card ?? []),
        );

        $missing = $contextBuilder->missingContext($context);

        if ($missing !== null) {
            $this->error("Session {$sessionNumber}: {$missing}");

            return false;
        }

        $this->reportContext($context);

        if ($this->option('dry-run')) {
            if ($this->option('show-prompt')) {
                $this->newLine();
                $this->line(view('ai.agents.adaptation.cold-open-repair.system-prompt', $context)->render());
                $this->newLine();
                $this->line(view('ai.agents.adaptation.cold-open-repair.prompt', $context)->render());
            }

            $this->info('Dry run only. The model was not called and nothing was saved.');

            return true;
        }

        $prompt = view('ai.agents.adaptation.cold-open-repair.prompt', $context)->render();
        $attemptPrompt = $prompt;
        $prose = '';
        $failures = [];

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = (new ColdOpenRepairAgent($context))->prompt($attemptPrompt, timeout: 300);
            } catch (Throwable $throwable) {
                $this->error($throwable->getMessage());

                return false;
            }

            $prose = BannedProseValidator::neutralizeBannedPunctuation(mb_trim((string) ($response['cold_open'] ?? '')));
            $failures = [
                ...$validator->failures($prose, $context['protagonist']),
                ...$bans->scan(['cold_open' => $prose], $voiceProfile, ['cold_open'])['errors'],
            ];

            if ($failures === []) {
                break;
            }

            if ($attempt < self::MAX_ATTEMPTS) {
                $this->warn("Attempt {$attempt} rejected: ".implode(' ', $failures));
                $attemptPrompt = $prompt
                    ."\n\n---\n\nYOUR PREVIOUS COLD OPEN WAS REJECTED. Fix every problem below and return the complete corrected cold_open.\n- "
                    .implode("\n- ", $failures)
                    ."\n\nREJECTED COLD OPEN:\n".$prose;
            }
        }

        $this->newLine();
        $this->line($prose);
        $this->newLine();
        $this->line($validator->wordCount($prose).' words.');

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error($failure);
            }

            $this->error("Session {$sessionNumber}: the candidate was not saved.");

            return false;
        }

        if (! $this->option('apply')) {
            $this->info("Session {$sessionNumber}: candidate passed. It was not saved. Pass --apply to store it.");

            return true;
        }

        $diagnosis['cold_open'] = $prose;
        $session->update(['entry_point_diagnosis' => $diagnosis]);
        $this->info("Stored the repaired cold open on {$story->slug} session {$sessionNumber}.");

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function reportContext(array $context): void
    {
        $this->info("Cold open repair — {$context['story_slug']}, session {$context['session_number']}");
        $this->line('Protagonist: '.$context['protagonist']);
        $this->line('Contract: '.ColdOpenRepairContext::CONTRACT_VERSION.' — Tom\'s first-output prompt, Boiler style and bans, no closing question');
        $this->line($context['narrator_rules'] !== ''
            ? 'Narrator rules loaded from the runtime prompt (Sections 4, 14, 18).'
            : 'No runtime narrator prompt is stored; the universal bans and voice profile still apply.');
        $this->line($context['first_scene_card'] !== []
            ? 'First scene card loaded: '.(string) ($context['first_scene_card']['moment_key'] ?? $context['first_scene_card']['title'] ?? 'card 1')
            : 'No finalized scene card is stored.');

        if ((int) $context['session_number'] > 1) {
            $this->line($context['previous_session_close'] !== []
                ? 'Previous session close loaded: '.implode(', ', array_keys($context['previous_session_close']))
                : 'No previous session close is stored; the cold open is repaired from this session alone.');
        }

        if ($context['cold_open_facts'] !== []) {
            $this->line('Facts the opening must say plainly:');

            foreach ($context['cold_open_facts'] as $fact) {
                $this->line('- '.$fact);
            }
        }

        $question = (string) $context['first_choice']['question'];

        if ($question !== '') {
            $this->line('Choice question kept off the prose: '.$question);
        }

        $this->line('Source events pulled: '.count($context['source_events']));
    }

    /**
     * @return array<string, string>
     */
    private function previousSessionClose(Story $story, int $sessionNumber, ColdOpenRepairContext $contextBuilder): array
    {
        if ($sessionNumber < 2) {
            return [];
        }

        $previous = $story->adaptation->sessionAdaptations()
            ->where('session_number', $sessionNumber - 1)
            ->first();
        $arcRow = collect((array) data_get($story->adaptation->story_session_map, 'arc_progression', []))
            ->first(fn ($row): bool => (int) data_get($row, 'session_number', data_get($row, 'session', 0)) === $sessionNumber);

        return $contextBuilder->previousSessionClose(
            $previous?->session_close_design,
            (string) data_get($arcRow, 'opens_with', ''),
        );
    }

    /**
     * @return list<array{position: int, title: string, content: string}>
     */
    private function sourceEvents(Story $story, int $sessionNumber, SessionEventLoader $eventLoader): array
    {
        return $eventLoader->forSession($story, $sessionNumber)
            ->map(fn ($event): array => [
                'position' => (int) $event->getAttribute('story_position'),
                'title' => (string) $event->title,
                'content' => (string) $event->content,
            ])
            ->all();
    }

    private function resolveStory(): ?Story
    {
        $identifier = (string) $this->argument('story');

        $query = Story::query()->with('adaptation');

        return is_numeric($identifier)
            ? $query->find((int) $identifier)
            : $query->where('slug', $identifier)->first();
    }
}
