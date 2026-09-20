<?php

declare(strict_types=1);

namespace App\Actions\Investor;

use App\Models\User;
use RuntimeException;

final readonly class CompleteInvestorAccessAction
{
    public function handle(User $user, string $email, string $password): User
    {
        if (! $user->isInvestor()) {
            throw new RuntimeException('Only investor accounts can complete this setup.');
        }

        $user->update([
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
            'credentials_set_at' => now(),
        ]);

        return $user->refresh();
    }
}
