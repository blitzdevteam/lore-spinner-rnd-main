<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Story;
use Illuminate\Console\Command;

final class InspectBoilerCommand extends Command
{
    protected $signature = 'stories:inspect-boiler
                            {story : Story ID or slug}
                            {--session= : Limit inspection to one session number}';

    protected $description = 'Inspect Boiler event roles and canonical moment cards';

    public function handle(): int
    {
        $story = $this->resolveStory();
        if ($story === null || $story->adaptation === null) {
            $this->error('Story or adaptation not found.');

            return self::FAILURE;
        }

        $sessions = $story->adaptation->sessionAdaptations()
            ->when(
                $this->option('session') !== null,
                fn ($query) => $query->where('session_number', (int) $this->option('session')),
            )
            ->orderBy('session_number')
            ->get();

        foreach ($sessions as $session) {
            $this->newLine();
            $this->info("Session {$session->session_number} — {$session->boiler_status->value}");
            if ($session->boiler_error) {
                $this->error($session->boiler_error);
            }

            $cut = $session->playableCut()
                ->with([
                    'eventRoles' => fn ($query) => $query->orderBy('event_id'),
                    'moments' => fn ($query) => $query->orderBy('moment_order'),
                ])
                ->first();

            if ($cut === null) {
                $this->warn('No normalized cut.');
                continue;
            }

            $this->line("Cut: {$cut->cut_key} / {$cut->status}");
            $this->table(
                ['Event', 'Role', 'Moment', 'Why', 'Reintroduction'],
                $cut->eventRoles->map(fn ($role): array => [
                    $role->event_id,
                    $role->primary_role,
                    $role->included_moment_key ?? '—',
                    $role->why,
                    $role->reintroduction_note ?? '—',
                ])->all(),
            );

            foreach ($cut->moments as $moment) {
                $card = (array) $moment->scene_card;
                $this->newLine();
                $this->line(sprintf(
                    '%d. %s — %s',
                    $moment->moment_order,
                    $moment->moment_key,
                    $card['title'] ?? '',
                ));
                $this->line('  Source events: ' . implode(', ', $moment->source_event_ids));
                $this->line('  Anchor event: ' . ($moment->anchor_event_id ?? 'none'));
                $this->line('  Choice IDs: ' . (implode(', ', (array) ($card['choice_ids'] ?? [])) ?: 'none'));
                $this->line('  Goal: ' . ($card['runtime_goal'] ?? ''));
                $this->line('  Desire: ' . ($card['player_desire'] ?? ''));
                $this->line('  Pressure: ' . ($card['live_pressure'] ?? ''));
                $this->line('  Extracts: ' . implode(' / ', (array) ($card['source_extracts'] ?? [])));
                $this->line('  Exit: ' . ($card['exit_condition'] ?? ''));
                $this->line('  Turn budget: ' . json_encode($card['turn_budget'] ?? []));
                $this->line('  Next: ' . ($card['next_moment_key'] ?? 'final'));
            }

            $this->line('Prompt chars: ' . mb_strlen((string) $session->runtime_narrator_prompt));
        }

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
