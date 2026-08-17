<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use App\Models\Game;
use App\Models\PlayerVoiceUsage;
use App\Models\Prompt;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class PlayerVoiceUsageRecorder
{
    public function __construct(private PlayerVoiceUsageCalculator $calculator) {}

    /**
     * @param  array<string, mixed>  $providerMetadata
     */
    public function record(
        Game $game,
        Prompt $prompt,
        string $provider,
        string $model,
        ?string $voiceId,
        string $text,
        int $billableUnits,
        string $billingUnit,
        string $audio,
        int $audioDurationMs,
        int $latencyMs,
        string $outputFormat,
        ?string $providerRequestId,
        ?string $providerTraceId,
        array $providerMetadata,
        ?CarbonInterface $occurredAt = null,
    ): PlayerVoiceUsage {
        if ($prompt->game_id !== $game->id) {
            throw new LogicException('Player voice usage prompt does not belong to the supplied game.');
        }

        $at = $occurredAt === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::instance($occurredAt)->utc();
        $billableUnits = max(0, $billableUnits);
        $pricing = $this->calculator->calculate(
            $provider,
            $model,
            $billableUnits,
            $billingUnit,
            $at,
        );
        $tokenUsage = DB::table('player_token_usages')
            ->where('prompt_id', $prompt->id);
        $tokenStoryCycle = $this->nullableInteger(
            (clone $tokenUsage)->value('story_cycle_number'),
        );
        $tokenSession = $this->nullableInteger(
            (clone $tokenUsage)->value('session_number'),
        );

        return PlayerVoiceUsage::query()->create([
            'game_id' => $game->id,
            'prompt_id' => $prompt->id,
            'prompt_reference_id' => $prompt->id,
            'story_id' => $game->story_id,
            'user_id' => $game->user_id,
            'story_cycle_number' => (int) ($tokenStoryCycle
                ?? $game->current_story_cycle_number),
            'session_number' => (int) ($tokenSession
                ?? $prompt->session_number
                ?? $game->current_session_number),
            'provider' => $provider,
            'model' => $model,
            'voice_id' => $voiceId,
            'output_format' => $outputFormat,
            'input_characters' => mb_strlen($text),
            'billable_units' => $billableUnits,
            'audio_bytes' => mb_strlen($audio, '8bit'),
            'audio_duration_ms' => max(0, $audioDurationMs),
            'latency_ms' => max(0, $latencyMs),
            ...$pricing,
            'provider_request_id' => $providerRequestId,
            'provider_trace_id' => $providerTraceId,
            'text_snapshot' => $text,
            'provider_metadata' => $providerMetadata,
            'occurred_at' => $at,
        ]);
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
