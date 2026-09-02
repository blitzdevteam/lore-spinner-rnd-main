<?php

declare(strict_types=1);

it('matches featured worlds play and coming soon button styles in the moods modal', function (): void {
    $moodsModal = file_get_contents(resource_path('js/components/ExploreByMood.vue'));
    $featuredCard = file_get_contents(resource_path('js/components/HomePortraitStoryCard.vue'));

    expect($featuredCard)
        ->toContain('background: #0e1f22')
        ->toContain('border: 0.5px solid #59c1ca')
        ->toContain('border-radius: 0.3125rem')
        ->toContain('height: 2.125rem')
        ->toContain('color: #8f8f8f')
        ->toContain('Coming Soon');

    expect($moodsModal)
        ->toContain('bg-[#0e1f22]')
        ->toContain('border-[#59c1ca]')
        ->toContain('rounded-[0.3125rem]')
        ->toContain('h-[2.125rem]')
        ->toContain('text-[#8f8f8f]')
        ->toContain('border-[#8f8f8f]')
        ->toContain('Coming Soon')
        ->not->toContain('bg-cta-fill');
});
