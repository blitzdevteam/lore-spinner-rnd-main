<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

it('renders the about us page', function () {
    $this->withoutMiddleware(App\Http\Middleware\RecordPageView::class)
        ->get(route('about-us'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('AboutUs'));
});

it('presents the about page story cycle and text sections', function () {
    $page = file_get_contents(resource_path('js/pages/AboutUs.vue'));

    expect($page)
        ->toContain('LoreSpinner is a new way to')
        ->toContain('step inside stories')
        ->toContain('What is LoreSpinner?')
        ->toContain('How it is changing world building')
        ->toContain('The future of storytelling')
        ->toContain('Use Your Imagination')
        ->toContain('Xen Responds')
        ->toContain('Every Journey Is Different')
        ->toContain('story-cycle.png')
        ->not->toContain('Every decision matters')
        ->not->toContain('Report them to the guards.');
});
