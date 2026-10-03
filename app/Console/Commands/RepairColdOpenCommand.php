<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Adaptation\ColdOpenRepairContext;
use App\Ai\Adaptation\ColdOpenRepairValidator;
use App\Ai\Agents\Adaptation\ColdOpenRepairAgent;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Services\Adaptation\SessionEventLoader;
use Illuminate\Console\Command;
use Throwable;

/**
 * Rewrite one session's cold open from the story's own diagnosis and voice.
 *
 * Prints a candidate for review. Pass --apply to store it. The entry-point
 * pipeline agent is unchanged.
 */
final class RepairColdOpenCommand extends Command
{
    protected $signature = 'stories:repair-cold-open
                            {story : Story ID or slug}
                            {--session=1 : Session number}
                            {--dry-run : Print the pulled facts without calling the model}
                            {--show-prompt : With --dry-run, print the full prompt}
                            {--apply : Store a valid cold open on the session diagnosis}';

    protected $description = 'Repair one story cold open from its diagnosis, voice, and source';

    public function handle(
        ColdOpenRepairContext $contextBuilder,
        ColdOpenRepairValidator $validator,
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

        $sessionNumber = filter_var($this->option('session'), FILTER_VALIDATE_INT);

        if ($sessionNumber === false || $sessionNumber < 1) {
            $this->error('Provide a positive --session number.');

            return self::FAILURE;
        }

        $session = $story->adaptation->sessionAdaptations()
            ->where('session_number', $sessionNumber)
            ->first();

        if ($session === null) {
            $this->error("Session {$sessionNumber} does not exist for this adaptation.");

            return self::FAILURE;
        }

        $diagnosis = (array) ($session->entry_point_diagnosis ?? []);
        $sourceEvents = $contextBuilder->sourceWindow(
            $this->sourceEvents($story, $sessionNumber, $eventLoader),
            (int) ($diagnosis['start_event_position'] ?? 0),
        );

        $context = $contextBuilder->build(
            $story->title,
            $story->slug,
            $sessionNumber,
            $contextBuilder->protagonistFor($story),
            (array) ($story->adaptation->voice_profile ?? []),
            $diagnosis,
            $sourceEvents,
        );

        $missing = $contextBuilder->missingContext($context);

        if ($missing !== null) {
            $this->error($missing);

            return self::FAILURE;
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

            return self::SUCCESS;
        }

        try {
            $response = (new ColdOpenRepairAgent($context))->prompt(
                view('ai.agents.adaptation.cold-open-repair.prompt', $context)->render(),
                timeout: 180,
            );
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $prose = mb_trim((string) ($response['cold_open'] ?? ''));
        $failures = $validator->failures($prose, $context['protagonist']);

        $this->newLine();
        $this->line($prose);
        $this->newLine();
        $this->line($validator->wordCount($prose).' words.');

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error($failure);
            }

            $this->error('The candidate was not saved.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->info('Candidate passed. It was not saved. Pass --apply to store it.');

            return self::SUCCESS;
        }

        $this->store($session, $diagnosis, $prose);
        $this->info("Stored the repaired cold open on {$story->slug} session {$sessionNumber}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function reportContext(array $context): void
    {
        $this->info("Cold open repair — {$context['story_slug']}, session {$context['session_number']}");
        $this->line('Protagonist: '.$context['protagonist']);
        $this->line('Contract: '.ColdOpenRepairContext::CONTRACT_VERSION.' — Tom\'s first-output prompt, with no closing question');

        if ((int) $context['session_number'] !== 1) {
            $this->warn('This contract is the first output a new player reads. Session '.$context['session_number'].' does not include the previous session handoff.');
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

    /**
     * @param  array<string, mixed>  $diagnosis
     */
    private function store(SessionAdaptation $session, array $diagnosis, string $prose): void
    {
        $diagnosis['cold_open'] = $prose;
        $session->update(['entry_point_diagnosis' => $diagnosis]);
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
