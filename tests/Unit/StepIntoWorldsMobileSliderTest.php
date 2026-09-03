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
        ->toContain('@media (min-width: 1024px) and (hover: hover)')
        ->toContain('transform: translateY(-6px) scale(1.02)');
});
