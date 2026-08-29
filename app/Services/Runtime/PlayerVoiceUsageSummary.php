<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use App\Models\PlayerVoiceUsage;
use Illuminate\Database\Eloquent\Builder;

final class PlayerVoiceUsageSummary
{
    /**
     * @return array{requests:int, input_characters:int, billable_units:int, audio_duration_ms:int}
     */
    public function forGame(string $gameId): array
    {
        return $this->summarize(
            PlayerVoiceUsage::query()->where('game_id', $gameId),
        );
    }

    /**
     * @return array{requests:int, input_characters:int, billable_units:int, audio_duration_ms:int}
     */
    public function forStoryCycle(string $gameId, int $storyCycleNumber): array
    {
        return $this->summarize(
            PlayerVoiceUsage::query()
                ->where('game_id', $gameId)
                ->where('story_cycle_number', $storyCycleNumber),
        );
    }

    /**
     * @param  Builder<PlayerVoiceUsage>  $query
     * @return array{requests:int, input_characters:int, billable_units:int, audio_duration_ms:int}
     */
    private function summarize(Builder $query): array
    {
        $totals = (clone $query)
            ->toBase()
            ->selectRaw(implode(', ', [
                'COUNT(*) AS requests',
                'COALESCE(SUM(input_characters), 0) AS input_characters',
                'COALESCE(SUM(billable_units), 0) AS billable_units',
                'COALESCE(SUM(audio_duration_ms), 0) AS audio_duration_ms',
            ]))
            ->first();

        return [
            'requests' => $this->integer($totals?->requests),
            'input_characters' => $this->integer($totals?->input_characters),
            'billable_units' => $this->integer($totals?->billable_units),
            'audio_duration_ms' => $this->integer($totals?->audio_duration_ms),
        ];
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
