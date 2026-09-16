<?php

declare(strict_types=1);

it('keeps inactive singleplayer header actions gray and active actions yellow', function (): void {
    $layout = file_get_contents(resource_path('js/layouts/GameplayLayout.vue'));
    $capsule = file_get_contents(resource_path('js/components/GameplayCapsuleButton.vue'));
    $appCss = file_get_contents(resource_path('css/app.css'));

    expect($layout)
        ->toContain('label="Audio"')
        ->toContain("'Show audio player' : 'Hide audio player'")
        ->toContain('@click="toggleMedia"')
        ->not->toContain('label="Narrate"')
        ->toContain('<LucideSettings v-else class="size-5 text-gray-300" />')
        ->toContain("label=\"Usage\"\n                                    label-tone=\"secondary\"")
        ->toContain('<LucideGauge v-else class="size-5 text-gray-300" />')
        ->toContain('<LucideNotebookText v-else class="size-5 text-gray-300" />')
        ->toContain(":class=\"settings.autoplay ? 'fill-secondary-300 text-secondary-300' : 'text-gray-300'\"");

    expect($capsule)
        ->toContain('.gameplay-capsule:hover .gameplay-capsule__icon :deep(svg),')
        ->toContain('.gameplay-capsule:focus-visible .gameplay-capsule__icon :deep(svg),')
        ->toContain('.gameplay-capsule--active .gameplay-capsule__icon :deep(svg)')
        ->toContain('color: var(--capsule-active-icon-color);');

    expect($appCss)
        ->toContain('.gameplay-header-glass--active')
        ->not->toContain('0 8px 30px rgba(84, 244, 218, 0.18)');
});
