<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Str;

final readonly class AssignUniqueUsernameAction
{
    public function handle(User $user): User
    {
        if (! blank($user->username)) {
            return $user;
        }

        $user->update([
            'username' => $this->generate($user),
        ]);

        return $user;
    }

    public function generate(User $user): string
    {
        $base = $this->base($user);
        $username = $base;
        $suffix = 1;

        while (User::query()->where('username', $username)->exists()) {
            $suffixText = (string) $suffix;
            $username = Str::substr($base, 0, 24 - mb_strlen($suffixText)).$suffixText;
            $suffix++;
        }

        return $username;
    }

    private function base(User $user): string
    {
        $fromName = $this->normalize((string) $user->first_name.(string) $user->last_name);

        if ($this->isValidBase($fromName)) {
            return Str::substr($fromName, 0, 20);
        }

        $emailLocal = mb_strstr($user->email, '@', true);
        $fromEmail = $this->normalize(is_string($emailLocal) ? $emailLocal : '');

        if ($this->isValidBase($fromEmail)) {
            return Str::substr($fromEmail, 0, 20);
        }

        return 'player';
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->toString();
    }

    private function isValidBase(string $value): bool
    {
        return mb_strlen($value) >= 3 && preg_match('/^[a-z]/', $value) === 1;
    }
}
