<?php

declare(strict_types=1);

namespace App\Actions\Investor;

use App\Models\Investor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RegisterInvestorAction
{
    /**
     * @param  array{
     *     first_name:string,
     *     last_name:string,
     *     username:string,
     *     email:string,
     *     password:string,
     *     notes?:string|null
     * }  $data
     */
    public function handle(array $data): Investor
    {
        if (User::query()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A player with this email already exists.',
            ]);
        }

        if (User::query()->where('username', mb_strtolower($data['username']))->exists()) {
            throw ValidationException::withMessages([
                'username' => 'This username is already taken.',
            ]);
        }

        return DB::transaction(function () use ($data): Investor {
            $user = User::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);

            return Investor::query()->create([
                'user_id' => $user->id,
                'access_enabled' => true,
                'notes' => $data['notes'] ?? null,
                'access_granted_at' => now(),
            ]);
        });
    }
}
