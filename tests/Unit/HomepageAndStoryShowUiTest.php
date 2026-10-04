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

it('orders the homepage like beta and omits featured worlds', function (): void {
    $homepage = file_get_contents(resource_path('js/pages/Index.vue'));
    $stepIntoWorlds = file_get_contents(resource_path('js/components/StepIntoWorlds.vue'));

    expect($homepage)
        ->toContain('<StoryChangesWithYou />')
        ->toContain('<StepIntoWorlds :worlds="worlds" />')
        ->toContain('<XenBanner />')
        ->toContain('<ExploreByMood :stories="visibleStories" />')
        ->toContain('<NewStoriesBanners :stories="visibleStories" />')
        ->toContain('<ComingSoonBanners :stories="comingSoonSectionStories" />')
        ->not->toContain('FeaturedWorldsGames');

    expect(mb_strpos($homepage, '<StoryChangesWithYou />'))
        ->toBeLessThan(mb_strpos($homepage, '<StepIntoWorlds :worlds="worlds" />'));
    expect(mb_strpos($homepage, '<StepIntoWorlds :worlds="worlds" />'))
        ->toBeLessThan(mb_strpos($homepage, '<XenBanner />'));
    expect(mb_strpos($homepage, '<XenBanner />'))
        ->toBeLessThan(mb_strpos($homepage, '<ExploreByMood :stories="visibleStories" />'));
    expect(mb_strpos($homepage, '<ExploreByMood :stories="visibleStories" />'))
        ->toBeLessThan(mb_strpos($homepage, '<NewStoriesBanners :stories="visibleStories" />'));
    expect(mb_strpos($homepage, '<NewStoriesBanners :stories="visibleStories" />'))
        ->toBeLessThan(mb_strpos($homepage, '<ComingSoonBanners :stories="comingSoonSectionStories" />'));

    expect($stepIntoWorlds)
        ->toContain('title="Step into Worlds"')
        ->not->toContain('title="Step into Stories"');
});

it('features the curated homepage story order', function (): void {
    $featured = file_get_contents(resource_path('js/data/homepageFeaturedStories.ts'));
    $hero = file_get_contents(resource_path('js/components/HeroBanner.vue'));

    expect($hero)->toContain("import sherlockImage from '@/assets/carosel/Sherlock Ultrawide hero banner.jpg'");

    $covers = file_get_contents(resource_path('js/data/storyCoverBySlug.ts'));

    expect($covers)->toContain("import sherlockCover from '@/assets/featured/sherlock-speckled-band.jpg'")
        ->toContain("import holyGrailCover from '@/assets/featured/the-quest-of-the-holy-grail.webp'")
        ->toContain("'the-quest-of-the-holy-grail': holyGrailCover")
        ->toContain("import senseAndSensibilityCover from '@/assets/featured/sense-and-sensibility.webp'")
        ->toContain("'sense-and-sensibility': senseAndSensibilityCover");

    $worldStoryCovers = file_get_contents(resource_path('js/data/storyTopMoodCoverBySlug.ts'));

    expect($worldStoryCovers)
        ->toContain("import speckledBandCover from '@/assets/newStories/speckled-band-landscape.jpg'")
        ->toContain("import blueCarbuncleCover from '@/assets/newStories/blue-carbuncle-wide.jpg'")
        ->toContain("import scandalBohemiaCover from '@/assets/newStories/scandal-in-bohemia-wide.jpg'")
        ->toContain("import emmaCover from '@/assets/newStories/emma-wide.jpg'")
        ->toContain("'the-adventure-of-the-speckled-band': speckledBandCover")
        ->toContain("'the-adventure-of-the-blue-carbuncle': blueCarbuncleCover")
        ->toContain("'a-scandal-in-bohemia': scandalBohemiaCover")
        ->toContain('emma: emmaCover');

    $continue = file_get_contents(resource_path('js/components/ContinueStories.vue'));

    expect($continue)
        ->toContain('resolveStoryTopMoodCover(s.slug, s.cover)')
        ->not->toContain('5_3SpeckledBandLandscapeTitleCard.png');

    $carousel = [
        'the-adventure-of-the-speckled-band',
        'the-wonderful-wizard-of-oz',
        'the-tell-tale-heart',
        'pride-and-prejudice',
    ];
    $newStories = [
        'peter-pan',
        'frankenstein',
        'emma',
        'the-invisible-man',
        'the-three-musketeers',
        'sir-gawain-and-the-green-knight',
        'around-the-world-in-eighty-days',
        'the-tell-tale-heart',
    ];
    $comingSoon = [
        'the-marvelous-land-of-oz',
        'sense-and-sensibility',
        'the-masque-of-the-red-death',
        'the-quest-of-the-holy-grail',
        'theseus-and-the-minotaur',
        '20000-leagues-under-the-sea',
    ];

    $previous = 0;

    foreach ($carousel as $slug) {
        $position = mb_strpos($hero, "slug: '{$slug}'");
        expect($position)->not->toBeFalse();
        expect($position)->toBeGreaterThan($previous);
        $previous = $position;
    }

    expect($hero)
        ->not->toContain("slug: 'dracula'")
        ->not->toContain("slug: 'robin-hood'")
        ->not->toContain("slug: 'the-masque-of-the-red-death'")
        ->not->toContain("slug: 'nocturne'")
        ->not->toContain("slug: 'jane-eyre'");

    $previous = mb_strpos($featured, 'HOMEPAGE_NEW_STORIES');
    expect($previous)->not->toBeFalse();

    foreach ($newStories as $slug) {
        $position = mb_strpos($featured, "slug: '{$slug}'", $previous);
        expect($position)->not->toBeFalse();
        expect($position)->toBeGreaterThan($previous);
        $previous = $position;
    }

    $previous = mb_strpos($featured, 'HOMEPAGE_COMING_SOON');
    expect($previous)->not->toBeFalse();

    foreach ($comingSoon as $slug) {
        $position = mb_strpos($featured, "slug: '{$slug}'", $previous);
        expect($position)->not->toBeFalse();
        expect($position)->toBeGreaterThan($previous);
        $previous = $position;
    }

    expect(config('stories.homepage_world_slugs'))->toBe([
        'baker-street',
        'jane-austen',
        'gothic-horrors',
    ]);
});

it('uses a shared 20px font for section titles on every page', function (): void {
    $appCss = file_get_contents(resource_path('css/app.css'));
    $sectionHeader = file_get_contents(resource_path('js/components/SectionHeader.vue'));

    expect($appCss)
        ->toContain("font-['Source_Sans_3',Inter,sans-serif] text-[20px]")
        ->not->toContain('text-[22px]')
        ->not->toContain('md:text-[1.625rem]');

    expect($sectionHeader)
        ->toContain('--section-header-title-size: 20px')
        ->not->toContain('--section-header-title-size: 22px')
        ->not->toContain('--section-header-title-size: 1.175rem');
});

it('adds top spacing to the hero branch graphic', function (): void {
    $heroBanner = file_get_contents(resource_path('js/components/HeroBanner.vue'));

    expect($heroBanner)
        ->toContain('src="/static-branch.jpg"')
        ->toContain('margin-top: 40px');
});

it('keeps hero carousel arrows vertically centered when the layout viewport narrows on hover-capable devices', function (): void {
    $heroBanner = file_get_contents(resource_path('js/components/HeroBanner.vue'));

    expect($heroBanner)
        ->toContain('max-width: 74.9375rem) and (hover: none)')
        ->toContain('bottom: 3.75rem');
});

it('stretches hero carousel arrows over the rendered image on tablet widths', function (): void {
    $heroBanner = file_get_contents(resource_path('js/components/HeroBanner.vue'));
    $navRule = mb_strstr($heroBanner, '.hero-nav {');

    expect($navRule)->toBeString()
        ->and($navRule)->toContain('inset: 0;')
        ->and($navRule)->not->toContain('aspect-ratio: 16 / 9;');
});

it('renders the split sign in, sign up, and password screens', function (): void {
    $login = file_get_contents(resource_path('js/pages/User/Authentication/Login.vue'));
    $register = file_get_contents(resource_path('js/pages/User/Authentication/Register.vue'));
    $forgot = file_get_contents(resource_path('js/pages/User/Authentication/ForgotPassword.vue'));

    expect($login)
        ->toContain('Continue Your Story')
        ->toContain('AuthSplitLayout')
        ->toContain('Forgot password?')
        ->toContain(':back-href="index().url"')
        ->not->toContain('AuthenticationLayout');

    expect($register)
        ->toContain('Step Into The Story')
        ->toContain('First Name')
        ->toContain('Last Name')
        ->not->toContain('Gender')
        ->not->toContain('Select your gender')
        ->toContain('name="password_confirmation"')
        ->toContain(':back-href="index().url"');

    expect($forgot)
        ->toContain('Forgot Password?')
        ->toContain('Check Your Email')
        ->toContain('Send Reset Link')
        ->toContain('Resend Link');
});

it('places the auth back button on the left side of the image', function (): void {
    $layout = file_get_contents(resource_path('js/components/auth/AuthSplitLayout.vue'));
    $backButtonOffset = mb_strpos($layout, 'auth-back-btn absolute top-4 left-4');
    $formOffset = mb_strpos($layout, 'max-w-[28.8125rem]');

    expect($backButtonOffset)->toBeInt()
        ->and($formOffset)->toBeInt()
        ->and($backButtonOffset)->toBeLessThan($formOffset)
        ->and($layout)->not->toContain('lg:left-[calc(50%+5.375rem)]');
});

it('renders story detail tabs as one pill track without a rating tab', function (): void {
    $tabs = file_get_contents(resource_path('js/components/story-play/StoryPlayTabPair.vue'));

    expect($tabs)
        ->toContain("label: 'Details'")
        ->toContain("label: 'Episodes'")
        ->toContain('rounded-full border border-solid border-[#3a3a3a]')
        ->toContain('story-play-tab--active')
        ->not->toContain('Rating & Reviews')
        ->not->toContain('w-[7.8125rem]');
});

it('opens a play mode modal from a single start story button', function (): void {
    $storyShow = file_get_contents(resource_path('js/pages/Stories/Show.vue'));
    $startCta = file_get_contents(resource_path('js/components/story-play/StoryPlayStartCta.vue'));
    $playMode = file_get_contents(resource_path('js/components/story-play/StoryPlayModeModal.vue'));

    expect($storyShow)
        ->toContain("'Start Story'")
        ->toContain('StoryPlayModeModal')
        ->toContain(':cover="coverImageUrl"')
        ->toContain('@solo="handleStartStory"')
        ->toContain('@group="handleMultiplayer"')
        ->not->toContain('label="Group Spin"')
        ->not->toContain('Enter The Story')
        ->not->toContain("'Single Player'")
        ->not->toContain('label="Multiplayer"');

    expect($startCta)
        ->toContain("label: 'Start Story'")
        ->not->toContain('Enter The Story');

    expect($playMode)
        ->toContain('How would you like to play?')
        ->toContain('Solo Spin')
        ->toContain('Play on your own')
        ->toContain('Group Spin')
        ->toContain('Play with friends')
        ->toContain('border-[#59c1ca]')
        ->toContain('text-[#ffbe58]')
        ->toContain('play-mode-cover-fade')
        ->toContain('mb-[-34px]');
});

it('uses a blurred story cover as the mobile-only background', function (): void {
    $storyShow = file_get_contents(resource_path('js/pages/Stories/Show.vue'));

    expect($storyShow)
        ->toContain('coverImageUrl')
        ->toContain('w-screen -translate-x-1/2 overflow-hidden')
        ->toContain('blur-[40px]')
        ->toContain('-top-20 bottom-0 z-0 lg:hidden')
        ->not->toContain('absolute inset-0 z-0 overflow-hidden lg:hidden');
});

it('renders story stats as separate boxes on mobile', function (): void {
    $statStrip = file_get_contents(resource_path('js/components/story-play/StoryPlayStatStrip.vue'));

    expect($statStrip)
        ->toContain('grid grid-cols-2 gap-2.5 lg:hidden')
        ->toContain('h-[68px] overflow-hidden rounded-xl')
        ->toContain('hidden w-full overflow-hidden rounded-xl bg-white/[0.04] lg:block');
});

it('aligns the desktop continue button with the bottom of the story poster', function (): void {
    $storyShow = file_get_contents(resource_path('js/pages/Stories/Show.vue'));

    expect($storyShow)
        ->toContain('lg:h-auto')
        ->toContain('lg:items-stretch')
        ->toContain('lg:absolute lg:inset-0')
        ->not->toContain('lg:h-svh lg:max-h-svh')
        ->not->toContain('sticky bottom-0');
});

it('places the story details back button in the page left corner instead of on the poster', function (): void {
    $storyShow = file_get_contents(resource_path('js/pages/Stories/Show.vue'));

    expect($storyShow)
        ->toContain('aria-label="Go back"')
        ->toContain('absolute top-4 left-4 z-30')
        ->not->toContain('pointer-events-auto absolute top-4 left-4');
});

it('puts restart and archive in the story actions menu after a game has started', function (): void {
    $storyShow = file_get_contents(resource_path('js/pages/Stories/Show.vue'));
    $topBar = file_get_contents(resource_path('js/components/story-play/StoryPlayTopBar.vue'));

    $restartModal = file_get_contents(resource_path('js/components/story-play/StoryPlayRestartModal.vue'));

    expect($storyShow)
        ->toContain(':show-game-menu="hasExistingGame"')
        ->toContain('@restart="handleRestartStory"')
        ->toContain('handleRestartStory')
        ->toContain('StoryPlayRestartModal')
        ->toContain('@confirm="confirmRestartStory"')
        ->toContain('reset as resetGame')
        ->toContain('resetGame.url(props.existingGameId)')
        ->not->toContain('Restart Story')
        ->not->toContain('window.confirm');

    expect($restartModal)
        ->toContain('Are you sure you want to restart this story?')
        ->toContain('Yes')
        ->toContain('No');

    expect($topBar)
        ->toContain('Story actions')
        ->toContain('Restart')
        ->toContain('Archive')
        ->toContain('v-if="showGameMenu"');
});

it('shows the fixed episode duration and builds the episode list from sessions', function (): void {
    $storyShow = file_get_contents(resource_path('js/pages/Stories/Show.vue'));
    $episodeCard = file_get_contents(resource_path('js/components/StoryEpisodeCard.vue'));
    $storyController = file_get_contents(app_path('Http/Controllers/StoryController.php'));

    expect($storyShow)
        ->toContain('Approx. 25m per episode')
        ->toContain('const episodes = computed(() => props.episodes ?? [])')
        ->toContain('v-for="episode in leftColumnEpisodes"')
        ->not->toContain('leftColumnChapters');

    expect($episodeCard)
        ->toContain('Events completed')
        ->toContain('Events {{ episode.events_played }}/')
        ->toContain('episode-lock.svg')
        ->not->toContain('Spins Earned');

    expect($storyController)
        ->toContain('StoryEpisodeCatalog::for($story, $existingGame)')
        ->toContain("'episodes' => \$episodes")
        ->not->toContain("'completedEpisodeNumbers'");

    expect(file_exists(resource_path('js/components/StoryChapterCard.vue')))->toBeFalse();
});

it('marks only completed episodes and derives the story progress percentage', function (): void {
    $storyShow = file_get_contents(resource_path('js/pages/Stories/Show.vue'));
    $episodeCard = file_get_contents(resource_path('js/components/StoryEpisodeCard.vue'));
    $titleProgress = file_get_contents(resource_path('js/components/story-play/StoryPlayTitleProgress.vue'));

    expect($episodeCard)
        ->toContain("type EpisodeStatus = 'completed' | 'in_progress' | 'not_started'")
        ->toContain("if (props.episode.events_played > 0)")
        ->toContain('line-clamp-2')
        ->not->toContain('text-justify');

    expect($storyShow)
        ->toContain('const progressPercent = computed')
        ->toContain('completedEpisodeCount.value / episodeCount.value')
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
