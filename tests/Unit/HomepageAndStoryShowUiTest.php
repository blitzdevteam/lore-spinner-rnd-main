<?php

declare(strict_types=1);

it('filters the library grid by all, single player, and multiplayer tabs', function (): void {
    $library = file_get_contents(resource_path('js/pages/Stories/Index.vue'));

    expect($library)
        ->toContain("type LibraryTab = 'all' | 'single' | 'multiplayer'")
        ->toContain("activeLibraryTab.value !== 'multiplayer'")
        ->toContain("activeLibraryTab.value !== 'single'")
        ->toContain('filteredStories.value.length + filteredRooms.value.length')
        ->toContain('LibraryMultiplayerCard')
        ->toContain('mood: activeMood.value || null')
        ->toContain('v-if="showMoodTopPicks"')
        ->toContain('px-5 pt-6 pb-10 sm:px-8 lg:px-16')
        ->toContain("activeLibraryTab.value === 'all'")
        ->not->toContain('MultiplayerRoomSection');
});

it('uses a shared 22px font for all homepage section titles', function (): void {
    $appCss = file_get_contents(resource_path('css/app.css'));
    $sectionHeader = file_get_contents(resource_path('js/components/SectionHeader.vue'));

    expect($appCss)
        ->toContain("font-['Source_Sans_3',Inter,sans-serif] text-[22px]")
        ->not->toContain('md:text-[1.625rem]');

    expect($sectionHeader)
        ->toContain('--section-header-title-size: 22px')
        ->not->toContain('--section-header-title-size: 1.175rem');
});

it('adds top spacing to the hero branch graphic', function (): void {
    $heroBanner = file_get_contents(resource_path('js/components/HeroBanner.vue'));

    expect($heroBanner)
        ->toContain('src="/static-branch.jpg"')
        ->toContain('margin-top: 40px');
});

it('labels the story details primary cta as single player', function (): void {
    $storyShow = file_get_contents(resource_path('js/pages/Stories/Show.vue'));
    $startCta = file_get_contents(resource_path('js/components/story-play/StoryPlayStartCta.vue'));

    expect($storyShow)
        ->toContain("'Single Player'")
        ->not->toContain('Enter The Story');

    expect($startCta)
        ->toContain("label: 'Single Player'")
        ->not->toContain('Enter The Story');
});

it('uses a blurred story cover as the mobile-only background', function (): void {
    $storyShow = file_get_contents(resource_path('js/pages/Stories/Show.vue'));

    expect($storyShow)
        ->toContain('coverImageUrl')
        ->toContain('w-screen -translate-x-1/2 overflow-hidden')
        ->toContain('blur-[40px]')
        ->toContain('md:top-[-2.5rem] lg:hidden')
        ->not->toContain('absolute inset-0 z-0 overflow-hidden lg:hidden');
});

it('renders story stats as separate boxes on mobile', function (): void {
    $statStrip = file_get_contents(resource_path('js/components/story-play/StoryPlayStatStrip.vue'));

    expect($statStrip)
        ->toContain('grid grid-cols-2 gap-2.5 lg:hidden')
        ->toContain('h-[68px] overflow-hidden rounded-xl')
        ->toContain('hidden w-full overflow-hidden rounded-xl bg-white/[0.04] lg:block');
});

it('places the story details back button in the page left corner instead of on the poster', function (): void {
    $storyShow = file_get_contents(resource_path('js/pages/Stories/Show.vue'));

    expect($storyShow)
        ->toContain('aria-label="Go back"')
        ->toContain('absolute top-4 left-4 z-30')
        ->not->toContain('pointer-events-auto absolute top-4 left-4');
});

it('exposes restart story on the story details page when a game already exists', function (): void {
    $storyShow = file_get_contents(resource_path('js/pages/Stories/Show.vue'));

    expect($storyShow)
        ->toContain('Restart Story')
        ->toContain('handleRestartStory')
        ->toContain('reset as resetGame')
        ->toContain('v-if="isPlayable"')
        ->toContain('!hasExistingGame || isRestarting')
        ->toContain('resetGame.url(props.existingGameId)');
});
