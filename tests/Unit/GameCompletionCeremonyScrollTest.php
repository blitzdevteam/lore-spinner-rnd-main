<?php

declare(strict_types=1);

it('keeps the reading position when an episode or story completion card appears', function () {
    $show = file_get_contents(resource_path('js/pages/User/Games/Show.vue'));
    $layout = file_get_contents(resource_path('js/layouts/GameplayLayout.vue'));

    expect($show)->not->toBeFalse()
        ->and($layout)->not->toBeFalse();

    $promptsLoop = mb_strpos($show, 'v-for="prompt in prompts"');
    $ceremony = mb_strpos($show, 'class="scroll-mt-28"');

    expect($promptsLoop)->not->toBeFalse()
        ->and($ceremony)->toBeGreaterThan($promptsLoop);

    expect($show)
        ->toContain('data-completion-ceremony')
        ->not->toContain('v-show="!(sessionComplete && ceremonyReady)"')
        ->not->toContain("scrollIntoView({ block: 'start'")
        ->toContain("scrollIntoView({ block: 'end', behavior: 'auto' })")
        ->toContain('if (ceremonyReady.value || sessionComplete.value)')
        ->toContain('function holdWindowScroll')
        ->toContain('function rememberReadingScroll')
        ->toContain('blurActiveElement()')
        ->toContain('holdWindowScroll(scrollY)')
        ->toContain('const scrollY = window.scrollY > 1 ? window.scrollY : readingScrollY');

    expect($layout)->toContain('overflow-anchor: none');
});

it('scrolls to the narration choices only while autoplay is on', function () {
    $show = file_get_contents(resource_path('js/pages/User/Games/Show.vue'));

    expect($show)->not->toBeFalse();

    expect($show)
        ->toContain('function scrollToNarrationEndOnce')
        ->toContain('if (!gameplaySettings.autoplay)')
        ->toContain("scrollIntoView({ block: 'end', behavior: 'auto' })")
        ->toContain('class="scroll-mb-42 md:scroll-mb-32"')
        ->toContain('scrollToNarrationEndOnce(latestPromptId.value)')
        ->toContain('scrollToNarrationEndOnce(committedPromptId)')
        ->not->toContain('function scrollToNarrationStartOnce')
        ->not->toContain('scrollToNarrationStartOnce');

    $autoplayOff = mb_strpos($show, '} else {
                    restoreWindowScroll(scrollY);');

    expect($autoplayOff)->not->toBeFalse();
});
