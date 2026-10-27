<?php

declare(strict_types=1);

it('implements the mobile worlds section as a horizontal snap slider', function (): void {
    $stepIntoWorlds = file_get_contents(resource_path('js/components/StepIntoWorlds.vue'));
    $worldCard = file_get_contents(resource_path('js/components/WorldCard.vue'));

    expect($stepIntoWorlds)
        ->toContain('scroll-snap-type: x mandatory')
        ->toContain('scroll-snap-align: start')
        ->toContain('width: 18.75rem')
        ->toContain('WORLDS_SLIDER_QUERY')
        ->toContain('(max-width: 1023px)')
        ->toContain('activeMobileId')
        ->toContain(':href="viewAllHref"')
        ->not->toContain(':count=')
        ->toContain('SectionHeader')
        ->not->toContain('worlds-view-all-mobile')
        ->not->toContain('worlds-section-header');

    expect($worldCard)
        ->toContain('height: calc(100% - 1.5rem)')
        ->toContain('.world-card--expanded');
});

it('uses bundled story posters for world card thumbnails', function (): void {
    $worldCard = file_get_contents(resource_path('js/components/WorldCard.vue'));

    expect($worldCard)
        ->toContain("import { resolveStoryCover } from '@/data/storyCoverBySlug'")
        ->toContain('resolveStoryCover(story.slug, story.cover)')
        ->not->toContain(':src="story.cover"');
});
