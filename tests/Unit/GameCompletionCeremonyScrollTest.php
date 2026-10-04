<?php

declare(strict_types=1);

it('keeps the reading position when an episode or story completion card appears', function () {
    $show = file_get_contents(resource_path('js/pages/User/Games/Show.vue'));

    expect($show)->not->toBeFalse();

    $promptsLoop = strpos($show, 'v-for="(prompt, index) in prompts"');
    $ceremony = strpos($show, 'class="scroll-mt-28"');

    expect($promptsLoop)->not->toBeFalse()
        ->and($ceremony)->toBeGreaterThan($promptsLoop);

    expect($show)
        ->toContain('data-completion-ceremony')
        ->not->toContain('v-show="!(sessionComplete && ceremonyReady)"')
        ->toContain('if (ceremonyReady.value)')
        ->toContain('function keepWindowScroll')
        ->toContain('keepWindowScroll(window.scrollY)')
        ->toContain('keepWindowScroll(scrollY)');
});
