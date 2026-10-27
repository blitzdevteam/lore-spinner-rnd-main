<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

it('exempts only the test activity begin endpoint from csrf validation', function () {
    $excludedPaths = app(ValidateCsrfToken::class)->getExcludedPaths();

    expect($excludedPaths)
        ->toContain('discord/activity/begin')
        ->not->toContain('user/games/*/begin');
});

it('serves the test activity when discord launches the site root', function () {
    config(['discord.application_id' => '100']);

    $this->get('/?frame_id=frame&instance_id=instance&platform=desktop')
        ->assertSuccessful()
        ->assertSee('Test only', false)
        ->assertSee('Solo test. You are alone in a small room. A lantern hangs by one shut door.', false)
        ->assertSee('src="/discord/activity.js?v=3"', false)
        ->assertSee('data-url="/discord/activity/begin?test=1"', false)
        ->assertDontSee('data-page=', false);
});

it('shows the lantern and door opening as a discord activity', function () {
    config(['discord.application_id' => '100']);

    $response = $this->get(route('discord.activity'));

    $response->assertSuccessful()
        ->assertSee('Solo test. You are alone in a small room. A lantern hangs by one shut door.', false)
        ->assertSee('Open the door', false)
        ->assertSee('Take the lantern', false)
        ->assertSee('A light in the dark', false)
        ->assertSee('Current objective', false)
        ->assertSee('Find a way out', false)
        ->assertSee('What will you do?', false)
        ->assertSee('src="/discord/activity.js?v=3"', false)
        ->assertHeader(
            'Content-Security-Policy',
            "frame-ancestors 'self' https://discord.com https://*.discord.com https://*.discordsays.com https://*.discordapp.com",
        );
});

it('points begin at the existing game begin route', function () {
    $gameId = '01m2cy18gknxs7v16k7b1gd6aj';

    $this->get(route('discord.activity', ['game' => $gameId]))
        ->assertSuccessful()
        ->assertSee('/user/games/'.$gameId.'/begin', false);
});

it('opens a test-only begin path without login', function () {
    $this->get(route('discord.activity', ['test' => 1]))
        ->assertSuccessful()
        ->assertSee('Test only', false)
        ->assertSee('Solo test. You are alone in a small room. A lantern hangs by one shut door.', false)
        ->assertSee('data-url="/discord/activity/begin?test=1"', false)
        ->assertDontSee('data-url="/user/games/', false);
});

it('returns the lantern scene from the test begin path without login', function () {
    $this->post(route('discord.activity.begin', ['test' => 1]))
        ->assertSuccessful()
        ->assertJsonPath('scene', 'gate')
        ->assertJsonPath('text', 'Solo test. You are alone in a small room. A lantern hangs by one shut door.')
        ->assertJsonPath('choices.0.label', 'Open the door')
        ->assertJsonPath('choices.1.label', 'Take the lantern');
});

it('refuses the activity begin path without the test flag', function () {
    $this->post(route('discord.activity.begin'))->assertNotFound();
});

it('still requires login for the normal begin route', function () {
    $this->post('/user/games/01m2cy18gknxs7v16k7b1gd6aj/begin')
        ->assertRedirect(route('user.authentication.login.create'));
});

it('ignores a game id that is not a game key', function () {
    $this->get(route('discord.activity', ['game' => '<script>']))
        ->assertSuccessful()
        ->assertSee('Add ?game= to call /user/games/{game}/begin.', false)
        ->assertDontSee('<script>', false);
});
