<?php

declare(strict_types=1);

it('keeps inactive singleplayer header actions gray and active actions yellow', function (): void {
    $layout = file_get_contents(resource_path('js/layouts/GameplayLayout.vue'));
    $capsule = file_get_contents(resource_path('js/components/GameplayCapsuleButton.vue'));

    expect($layout)
        ->toContain('<LucideSettings v-else class="size-5 text-gray-300" />')
        ->toContain("label=\"Usage\"\n                                    label-tone=\"secondary\"")
        ->toContain('<LucideGauge v-else class="size-5 text-gray-300" />')
        ->toContain('<LucideNotebookText v-else class="size-5 text-gray-300" />')
        ->toContain(":class=\"settings.autoplay ? 'text-secondary-300' : 'text-gray-300'\"")
        ->not->toContain('fill-secondary-300');

    expect($capsule)
        ->toContain('.gameplay-capsule:hover .gameplay-capsule__icon :deep(svg),')
        ->toContain('.gameplay-capsule:focus-visible .gameplay-capsule__icon :deep(svg),')
        ->toContain('.gameplay-capsule--active .gameplay-capsule__icon :deep(svg)')
        ->toContain('color: var(--capsule-active-icon-color);');
});
