<?php

declare(strict_types=1);

namespace App\Actions\Investor;

use App\Models\User;
use RuntimeException;

final readonly class UpdateInvestorAccessAction
{
    public function __construct(private SyncInvestorPlanAccessAction $syncInvestorPlanAccess) {}

    public function block(User $user): User
    {
        $this->assertInvestor($user);

        $user->update([
            'access_enabled' => false,
            'access_blocked_at' => now(),
        ]);

        $this->syncInvestorPlanAccess->revoke($user);

        return $user->refresh();
    }

    public function restore(User $user): User
    {
        $this->assertInvestor($user);

        $grantedAt = now();
        $durationMonths = max(1, $user->duration_months);

        $user->update([
            'access_enabled' => true,
            'access_blocked_at' => null,
            'access_granted_at' => $grantedAt,
            'expires_at' => $grantedAt->copy()->addMonthsNoOverflow($durationMonths),
        ]);

        $this->syncInvestorPlanAccess->grant($user->refresh());

        return $user->refresh();
    }

    private function assertInvestor(User $user): void
    {
        if (! $user->isInvestor()) {
            throw new RuntimeException('Test access can only be updated for investor accounts.');
        }
    }
}
