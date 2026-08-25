<?php

declare(strict_types=1);

it('rotates the arbiter chat send arrow upward to match the design', function () {
    $template = file_get_contents(resource_path('js/pages/Arbiter.vue'));

    expect($template)
        ->toContain('aria-label="Send message"')
        ->toContain('<img :src="sendIcon" alt="" class="rotate-90" aria-hidden="true" />');
});

it('keeps arbiter rail tabs close to the top of the display', function () {
    $styles = file_get_contents(resource_path('js/pages/Arbiter.vue'));

    expect($styles)
        ->toContain("padding-top: 1.5625rem;")
        ->not->toContain('padding-top: 7.1875rem;');
});

it('pins the arbiter side rails while the story column scrolls', function () {
    $styles = file_get_contents(resource_path('js/pages/Arbiter.vue'));

    expect($styles)
        ->toContain('height: 100svh;')
        ->toContain('overflow-y: auto;')
        ->toContain('ref="storyColumn"');
});
