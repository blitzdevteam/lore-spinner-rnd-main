<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shares null auth so guests receive the header login and sign up actions', function () {
    $this->get(route('index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Index')
            ->where('auth', null)
        );
});

it('shares profile fields the header avatar menu uses after login', function () {
    $user = User::factory()->withCompleteProfile()->markEmailAsVerified()->create([
        'username' => 'navtester',
        'avatar' => null,
    ]);

    $this->actingAs($user)
        ->get(route('index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Index')
            ->where('auth.username', 'navtester')
            ->where('auth.avatar', null)
        );
});
