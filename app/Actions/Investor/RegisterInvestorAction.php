<?php

declare(strict_types=1);

namespace App\Actions\Investor;

use App\Enums\UserType;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RegisterInvestorAction
{
    public function __construct(private SyncInvestorPlanAccessAction $syncInvestorPlanAccess) {}

    /**
     * @param  array{
     *     first_name:string,
     *     last_name:string,
     *     subscription_plan_id:int,
     *     duration_months:int,
     *     notes?:string|null
     * }  $data
     */
    public function handle(array $data): User
    {
        $plan = SubscriptionPlan::query()->find($data['subscription_plan_id']);

        if (! $plan instanceof SubscriptionPlan) {
            throw ValidationException::withMessages([
                'subscription_plan_id' => 'Select a subscription plan for this investor.',
            ]);
        }

        $durationMonths = User::normalizeDurationMonths((int) $data['duration_months']);

        return DB::transaction(function () use ($data, $plan, $durationMonths): User {
            $grantedAt = now();
            $user = User::query()->create([
                'type' => UserType::Investor,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'username' => $this->uniqueUsername($data['first_name'], $data['last_name']),
                'email' => $this->pendingEmail(),
                'password' => Str::password(20),
                'is_active' => true,
                'subscription_plan_id' => $plan->id,
                'duration_months' => $durationMonths,
                'access_enabled' => true,
                'notes' => $data['notes'] ?? null,
                'access_granted_at' => $grantedAt,
                'expires_at' => User::accessExpiresAt($durationMonths, $grantedAt),
                'credentials_set_at' => null,
            ]);

            $this->syncInvestorPlanAccess->grant($user);

            return $user->refresh()->load('plan');
        });
    }

    private function uniqueUsername(string $firstName, string $lastName): string
    {
        $base = Str::slug($firstName.'-'.$lastName);

        if ($base === '') {
            $base = 'investor';
        }

        $username = $base;
        $suffix = 1;

        while (User::query()->where('username', $username)->exists()) {
            $username = $base.'-'.$suffix;
            $suffix++;
        }

        return $username;
    }

    private function pendingEmail(): string
    {
        return 'investor-'.Str::lower((string) Str::ulid()).'@'.User::PENDING_EMAIL_DOMAIN;
    }
}
