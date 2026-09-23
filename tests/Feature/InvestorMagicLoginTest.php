<?php

declare(strict_types=1);

use App\Actions\Investor\UpdateInvestorAccessAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('asks a new investor to set an email and password from the magic link', function (): void {
    $investor = User::factory()->investor()->pendingSetup()->create();
    $url = $investor->magicLoginUrl();

    expect($url)->not->toBeNull();

    $this->get($url)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/InvestorAccess')
            ->where('firstName', $investor->first_name)
            ->has('submitUrl'));

    $this->assertGuest('user');
});

it('lets an investor finish setup from the magic link and sign in', function (): void {
    $investor = User::factory()->investor()->pendingSetup()->create();
    $url = $investor->magicLoginUrl();

    expect($url)->not->toBeNull();

    $this->from($url)
        ->post($url, [
            'email' => 'mira.investor@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('index'));

    $investor->refresh()->load('plan');

    expect($investor->hasCompletedSetup())->toBeTrue()
        ->and($investor->email)->toBe('mira.investor@example.test')
        ->and(Hash::check('password', $investor->password))->toBeTrue();

    $this->assertAuthenticatedAs($investor, 'user');

    $this->get(route('index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.investor_welcome.plan_name', $investor->plan?->name)
            ->where('flash.investor_welcome.duration', '1 month')
            ->where('flash.investor_welcome.expires_on', $investor->expires_at?->format('F j, Y')));

    $this->get(route('index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('flash.investor_welcome', null));
});

it('rejects an unsigned investor access link', function (): void {
    $investor = User::factory()->investor()->pendingSetup()->create();

    $this->get(route('investor.access', $investor))
        ->assertForbidden();

    $this->assertGuest('user');
});

it('rejects a blocked investor magic login link', function (): void {
    $investor = User::factory()->investor()->pendingSetup()->create();
    $url = $investor->magicLoginUrl();

    expect($url)->not->toBeNull();

    app(UpdateInvestorAccessAction::class)->block($investor);

    $this->get($url)
        ->assertRedirect(route('pricing'));

    $this->assertGuest('user');
});

it('signs a completed investor in with the same magic login link', function (): void {
    $investor = User::factory()->investor()->create();
    $url = $investor->magicLoginUrl();

    expect($url)->not->toBeNull()
        ->and($investor->hasCompletedSetup())->toBeTrue();

    $this->get($url)
        ->assertRedirect(route('index'));

    $this->assertAuthenticatedAs($investor, 'user');

    $this->get(route('index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('flash.investor_welcome', null));
});

it('replaces an existing player session when a completed magic login link is used', function (): void {
    $investor = User::factory()->investor()->create();
    $otherUser = User::factory()->withCompleteProfile()->create(['is_active' => true]);
    $url = $investor->magicLoginUrl();

    expect($url)->not->toBeNull();

    $this->actingAs($otherUser, 'user')
        ->get($url)
        ->assertRedirect(route('index'));

    $this->assertAuthenticatedAs($investor, 'user');
});
