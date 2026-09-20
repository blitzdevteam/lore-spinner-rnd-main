<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks investor test accounts whose access window has ended', function (): void {
    $active = User::factory()->investor()->create();
    $expired = User::factory()->investor()->expired()->create();

    $this->artisan('investors:expire-access')
        ->expectsOutputToContain('Blocked 1 expired investor account(s).')
        ->assertSuccessful();

    expect($active->fresh()?->access_enabled)->toBeTrue()
        ->and($active->fresh()?->hasActiveInvestorAccess())->toBeTrue()
        ->and($expired->fresh()?->access_enabled)->toBeFalse()
        ->and($expired->fresh()?->access_blocked_at)->not->toBeNull()
        ->and($expired->fresh()?->hasActiveInvestorAccess())->toBeFalse();
});

it('does nothing when no investor windows have expired', function (): void {
    User::factory()->investor()->create();

    $this->artisan('investors:expire-access')
        ->expectsOutputToContain('Blocked 0 expired investor account(s).')
        ->assertSuccessful();
});
