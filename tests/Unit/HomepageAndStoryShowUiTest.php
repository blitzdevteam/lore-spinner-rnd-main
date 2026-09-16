<?php

declare(strict_types=1);

it('renders the library as a single story grid without multiplayer tabs or rooms', function (): void {
    $library = file_get_contents(resource_path('js/pages/Stories/Index.vue'));
    $storyController = file_get_contents(app_path('Http/Controllers/StoryController.php'));

    expect($library)
        ->toContain('HomePortraitStoryCard')
        ->toContain('v-if="showMoodTopPicks"')
        ->not->toContain('LibraryMultiplayerCard')
        ->not->toContain('Single Player')
        ->not->toContain('Join Room')
        ->not->toContain('multiplayerRooms');

    expect($storyController)
        ->not->toContain('multiplayerRooms');
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

it('shows the fixed episode duration and data-driven episode completion', function (): void {
    $storyShow = file_get_contents(resource_path('js/pages/Stories/Show.vue'));
    $chapterCard = file_get_contents(resource_path('js/components/StoryChapterCard.vue'));
    $storyController = file_get_contents(app_path('Http/Controllers/StoryController.php'));

    expect($storyShow)
        ->toContain('Approx. 25m per episode')
        ->toContain('completedEpisodeNumbers')
        ->toContain(':is-completed="completedEpisodeNumbers.includes(chapter.position)"');

    expect($chapterCard)
        ->not->toContain('Spins Earned')
        ->toContain('if (props.isCompleted)')
        ->toContain('Events completed');

    expect($storyController)
        ->toContain("'completedEpisodeNumbers'")
        ->toContain('$existingGame->current_session_complete');
});

it('marks only completed episodes and derives the story progress percentage', function (): void {
    $storyShow = file_get_contents(resource_path('js/pages/Stories/Show.vue'));
    $chapterCard = file_get_contents(resource_path('js/components/StoryChapterCard.vue'));
    $titleProgress = file_get_contents(resource_path('js/components/story-play/StoryPlayTitleProgress.vue'));

    expect($chapterCard)
        ->toContain("statusIcon = computed<'locked' | 'completed' | null>")
        ->toContain("return props.isCompleted ? 'completed' : null")
        ->toContain('line-clamp-4')
        ->not->toContain('text-justify')
        ->not->toContain('mt-auto');

    expect($storyShow)
        ->toContain('const progressPercent = computed')
        ->toContain('completedEpisodeNumbers.value.length / episodeCount.value')
        ->toContain(':progress-percent="progressPercent"');

    expect($titleProgress)
        ->not->toContain("padStart(2, '0')");
});

it('adds pricing to the home navigation and keeps subscription usage in the profile menu only', function (): void {
    $headerMenu = file_get_contents(resource_path('js/components/HomeHeaderMenu.vue'));
    $mobileMenu = file_get_contents(resource_path('js/components/HomeHeaderMobileMenu.vue'));
    $profile = file_get_contents(resource_path('js/components/HomeHeaderProfile.vue'));

    expect($headerMenu)
        ->toContain('Pricing')
        ->toContain('text-[0.9375rem]');

    expect($mobileMenu)
        ->toContain('Pricing');

    expect($profile)
        ->toContain('subscription_usage')
        ->toContain('subscription-summary')
        ->not->toContain('Remaining subscription allowances')
        ->not->toContain('w-[9.5rem]');
});
