<?php

declare(strict_types=1);

namespace App\Actions\Investor;

use App\Models\Investor;

final readonly class UpdateInvestorAccessAction
{
    public function block(Investor $investor): Investor
    {
        $investor->update([
            'access_enabled' => false,
            'access_blocked_at' => now(),
        ]);

        return $investor->refresh();
    }

    public function restore(Investor $investor): Investor
    {
        $investor->update([
            'access_enabled' => true,
            'access_blocked_at' => null,
            'access_granted_at' => now(),
        ]);

        return $investor->refresh();
    }
}
