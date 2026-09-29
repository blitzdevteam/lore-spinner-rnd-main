<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;

final readonly class MarkPlayerWelcomeAction
{
    /**
     * @return array{first_name: string}|null
     */
    public function handle(User $user): ?array
    {
        if ($user->isInvestor() || $user->welcomed_at !== null) {
            return null;
        }

        $user->forceFill([
            'welcomed_at' => now(),
        ])->save();

        return [
            'first_name' => mb_trim((string) $user->first_name),
        ];
    }
}
