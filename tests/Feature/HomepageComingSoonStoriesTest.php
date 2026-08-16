<?php

declare(strict_types=1);

use App\Enums\ComingSoonStory\ComingSoonStorySectionEnum;
use App\Models\ComingSoonStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('loads only active coming soon stories ordered by position', function () {
    ComingSoonStory::factory()->create([
        'section' => ComingSoonStorySectionEnum::HERO,
        'position' => 2,
        'title' => 'Second Hero Story',
        'is_active' => true,
    ]);

    ComingSoonStory::factory()->create([
        'section' => ComingSoonStorySectionEnum::HERO,
        'position' => 1,
        'title' => 'First Hero Story',
        'is_active' => true,
    ]);

    ComingSoonStory::factory()->create([
        'section' => ComingSoonStorySectionEnum::HERO,
        'position' => 0,
        'title' => 'Inactive Hero Story',
        'is_active' => false,
    ]);

    $this->get(route('index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Index')
                ->has('comingSoonStories', 2)
                ->where('comingSoonStories.0.title', 'First Hero Story')
                ->where('comingSoonStories.0.section', ComingSoonStorySectionEnum::HERO->value)
                ->where('comingSoonStories.1.title', 'Second Hero Story')
                ->etc()
        );
});
