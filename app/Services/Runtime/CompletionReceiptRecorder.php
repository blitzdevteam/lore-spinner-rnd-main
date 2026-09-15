<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use App\Enums\CompletionReceiptMode;
use App\Models\CompletionReceipt;
use App\Models\Game;

final class CompletionReceiptRecorder
{
    public function __construct(private readonly CompletionReceiptBuilder $builder) {}

    public function snapshot(Game $game): ?CompletionReceipt
    {
        $hydrated = $this->builder->hydrate($game->fresh() ?? $game);
        $payload = $this->builder->build($hydrated);

        if ($payload === null) {
            return null;
        }

        $mode = CompletionReceiptMode::from($payload['mode']);

        return CompletionReceipt::query()->updateOrCreate(
            [
                'game_id' => $hydrated->id,
                'story_cycle_number' => (int) $hydrated->current_story_cycle_number,
                'session_number' => (int) ($payload['session_number'] ?? $hydrated->current_session_number ?? 1),
                'mode' => $mode,
            ],
            [
                'user_id' => $hydrated->user_id,
                'story_id' => $hydrated->story_id,
                'receipt' => $payload,
            ],
        );
    }
}
