<?php

declare(strict_types=1);

namespace App\Services\Runtime;

/**
 * The single boundary between player usage and subscription entitlements.
 *
 * Until billing is connected, every player receives the configured launch
 * allowance. A subscription provider can later resolve a user-specific plan
 * here without changing the game UI or usage ledger.
 */
final class GameplayAllowanceResolver
{
    /**
     * @return array{turns:int, voice_duration_ms:int}
     */
    public function forUser(int $userId): array
    {
        return [
            'turns' => $this->nonNegativeInteger(config('gameplay-usage.default_plan.turns', 250)),
            'voice_duration_ms' => $this->nonNegativeInteger(
                config('gameplay-usage.default_plan.voice_minutes', 60),
            ) * 60_000,
        ];
    }

    private function nonNegativeInteger(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }
}
