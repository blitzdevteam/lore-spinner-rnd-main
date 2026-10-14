<?php

declare(strict_types=1);

use App\Support\PasswordRequirements;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * @param  array<string, mixed>  $data
 */
function passwordErrors(array $data): ?string
{
    $validator = Validator::make($data, [
        'password' => ['required', 'confirmed', ...PasswordRequirements::rules()],
    ]);

    if ($validator->passes()) {
        return null;
    }

    return $validator->errors()->first('password');
}

it('accepts a strong password that is not common or sequential', function (): void {
    expect(passwordErrors([
        'password' => 'n7K!mQ2vLx9p',
        'password_confirmation' => 'n7K!mQ2vLx9p',
    ]))->toBeNull();
});

it('accepts an ordinary word that is not a known password', function (): void {
    expect(passwordErrors([
        'password' => 'firstuser',
        'password_confirmation' => 'firstuser',
    ]))->toBeNull();
});

it('rejects weak and predictable passwords', function (string $password, string $message): void {
    expect(passwordErrors([
        'password' => $password,
        'password_confirmation' => $password,
    ]))->toContain($message);
})->with([
    'common' => ['password', 'too common'],
    'common with digits' => ['Password123!', 'too common'],
    'keyboard walk' => ['qwertyuiop', 'too common'],
    'sequential digits' => ['23456789', 'sequential numbers'],
    'descending digits' => ['9876word', 'sequential numbers'],
    'sequential letters' => ['hijklmno', 'sequential letters'],
    'padded letter sequence' => ['!!abcd!!', 'sequential letters'],
    'repeated character' => ['doorrrrr', 'repeat the same character'],
    'too short' => ['n7K!mQ2', 'at least 8'],
]);

it('rejects a password that has appeared in a data breach', function (): void {
    config(['auth.check_breached_passwords' => true]);

    $password = 'n7K!mQ2vLx9p';
    $hash = mb_strtoupper(sha1($password));
    $prefix = mb_substr($hash, 0, 5);
    $suffix = mb_substr($hash, 5);

    Http::fake([
        'api.pwnedpasswords.com/range/'.$prefix => Http::response($suffix.':3'."\n"),
    ]);

    expect(passwordErrors([
        'password' => $password,
        'password_confirmation' => $password,
    ]))->toContain('data breach');
});
