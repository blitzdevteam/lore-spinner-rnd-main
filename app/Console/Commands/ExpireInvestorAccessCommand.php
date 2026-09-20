<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Investor\UpdateInvestorAccessAction;
use App\Models\User;
use Illuminate\Console\Command;

final class ExpireInvestorAccessCommand extends Command
{
    protected $signature = 'investors:expire-access';

    protected $description = 'Block investor test accounts whose access window has ended';

    public function handle(UpdateInvestorAccessAction $updateInvestorAccess): int
    {
        $expiredInvestors = User::query()
            ->investors()
            ->where('access_enabled', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expiredInvestors as $investor) {
            $updateInvestorAccess->block($investor);
        }

        $this->info("Blocked {$expiredInvestors->count()} expired investor account(s).");

        return self::SUCCESS;
    }
}
