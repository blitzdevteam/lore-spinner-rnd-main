<?php

declare(strict_types=1);

it('keeps gameplay rendering compatible with Safari', function (): void {
    $streamingRenderer = file_get_contents(resource_path('js/components/AnimatedStreamingHtml.vue'));
    $showPage = file_get_contents(resource_path('js/pages/User/Games/Show.vue'));
    $arbiterPage = file_get_contents(resource_path('js/pages/Arbiter.vue'));
    $chaosTextToSpeech = file_get_contents(resource_path('js/composables/useChaosTextToSpeech.ts'));

    expect($streamingRenderer)
        ->toContain('window.requestAnimationFrame(flushHtml)')
        ->toContain('<div v-html="renderedHtml" />')
        ->not->toContain('document.createElement')
        ->not->toContain('renderHtmlNode');

    expect($showPage)
        ->toContain('hasVisibleStreamedText')
        ->not->toContain('stripHtml(streamingPrompt.response)');

    expect($arbiterPage)
        ->not->toContain('.at(');

    expect($chaosTextToSpeech)
        ->toContain("typeof navigator !== 'undefined'");
});
