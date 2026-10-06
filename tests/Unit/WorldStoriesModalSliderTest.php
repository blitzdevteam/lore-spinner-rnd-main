<?php

declare(strict_types=1);

it('shows available stories in a horizontal snap slider inside the world modal', function (): void {
    $modal = file_get_contents(resource_path('js/components/WorldStoriesModal.vue'));

    expect($modal)
        ->toContain('world-modal__stories-slider')
        ->toContain('HomePortraitStoryCard')
        ->toContain('resolveStoryCover')
        ->toContain('--story-portrait-card-width')
        ->toContain('scroll-snap-type: x mandatory')
        ->toContain('scroll-snap-align: start')
        ->toContain('overflow-x: auto')
        ->toContain('flex-wrap: nowrap')
        ->toContain('rgb(0 0 0 / 0.4)')
        ->not->toContain('rgb(0 0 0 / 0.97)')
        ->not->toContain('WorldAvailableStoryCard')
        ->not->toContain('resolveStoryTopMoodCover')
        ->not->toContain('flex flex-wrap gap-2.5');
});
