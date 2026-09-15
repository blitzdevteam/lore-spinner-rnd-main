<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use App\Enums\CompletionReceiptMode;
use App\Models\CompletionReceipt;
use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Collection;

final class AchievementLibrary
{
    public function __construct(
        private readonly CompletionReceiptRecorder $recorder,
    ) {}

    /**
     * @return list<array{
     *     id: int,
     *     title: string,
     *     slug: string,
     *     cover: string,
     *     total_sessions: int,
     *     episode_count: int,
     *     story_complete: bool,
     *     episodes: list<array<string, mixed>>,
     *     story_card: array<string, mixed>|null
     * }>
     */
    public function forUser(User $user): array
    {
        $this->backfillLiveSessions($user);

        /** @var Collection<int, CompletionReceipt> $receipts */
        $receipts = CompletionReceipt::query()
            ->where('user_id', $user->id)
            ->with(['story.adaptation' => fn ($query) => $query->withCount('sessionAdaptations')])
            ->orderByDesc('id')
            ->get()
            ->unique(fn (CompletionReceipt $row): string => $row->story_id.'|'.$row->mode->value.'|'.$row->session_number)
            ->values();

        return $receipts
            ->groupBy('story_id')
            ->map(fn (Collection $rows): array => $this->storyPayload($rows))
            ->sortBy(fn (array $story): string => mb_strtolower($story['title']))
            ->values()
            ->all();
    }

    private function backfillLiveSessions(User $user): void
    {
        $liveGames = Game::query()
            ->where('user_id', $user->id)
            ->where('current_session_complete', true)
            ->get();

        foreach ($liveGames as $game) {
            $alreadySaved = CompletionReceipt::query()
                ->where('game_id', $game->id)
                ->where('story_cycle_number', $game->current_story_cycle_number)
                ->where('session_number', (int) ($game->current_session_number ?? 1))
                ->exists();

            if ($alreadySaved) {
                continue;
            }

            $this->recorder->snapshot($game);
        }
    }

    /**
     * @param  Collection<int, CompletionReceipt>  $rows
     * @return array{
     *     id: int,
     *     title: string,
     *     slug: string,
     *     cover: string,
     *     total_sessions: int,
     *     episode_count: int,
     *     story_complete: bool,
     *     episodes: list<array<string, mixed>>,
     *     story_card: array<string, mixed>|null
     * }
     */
    private function storyPayload(Collection $rows): array
    {
        /** @var CompletionReceipt $first */
        $first = $rows->first();
        $story = $first->story;
        $totalSessions = (int) ($story?->adaptation?->session_adaptations_count ?? $rows->max('session_number') ?? 0);

        $episodes = $rows
            ->filter(fn (CompletionReceipt $row): bool => $row->mode === CompletionReceiptMode::Session)
            ->sortBy('session_number')
            ->values()
            ->map(fn (CompletionReceipt $row): array => $this->cardPayload($row))
            ->all();

        $storyRow = $rows->first(
            fn (CompletionReceipt $row): bool => $row->mode === CompletionReceiptMode::Story,
        );

        return [
            'id' => (int) $story?->id,
            'title' => (string) ($story?->title ?? $first->receipt['story_title'] ?? 'Untitled story'),
            'slug' => (string) ($story?->slug ?? ''),
            'cover' => (string) ($story?->getFirstMediaUrl('cover') ?? ''),
            'total_sessions' => $totalSessions,
            'episode_count' => count($episodes),
            'story_complete' => $storyRow instanceof CompletionReceipt,
            'episodes' => $episodes,
            'story_card' => $storyRow instanceof CompletionReceipt ? $this->cardPayload($storyRow) : null,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     mode: string,
     *     session_number: int,
     *     label: string,
     *     preview: string|null,
     *     earned_at: string|null,
     *     receipt: array<string, mixed>
     * }
     */
    private function cardPayload(CompletionReceipt $row): array
    {
        $receipt = is_array($row->receipt) ? $row->receipt : [];
        $preview = $receipt['final_line'] ?? $receipt['share_line'] ?? null;

        return [
            'id' => $row->id,
            'mode' => $row->mode->value,
            'session_number' => $row->session_number,
            'label' => $row->mode === CompletionReceiptMode::Story
                ? 'Story complete'
                : 'Episode '.$row->session_number,
            'preview' => is_string($preview) && $preview !== '' ? $preview : null,
            'earned_at' => $row->updated_at?->toIso8601String(),
            'receipt' => $receipt,
        ];
    }
}
