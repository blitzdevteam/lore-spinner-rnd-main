<?php

declare(strict_types=1);

it('defaults the listen pill on so single-player gameplay keeps play narration', function (): void {
    $card = file_get_contents(resource_path('js/components/GameplayChatCard.vue'));
    $show = file_get_contents(resource_path('js/pages/User/Games/Show.vue'));

    expect($card)
        ->toContain('voiceOverEnabled: true')
        ->toContain('v-if="voiceOverEnabled"')
        ->toContain('Play Narration')
        ->toContain('handleListenAgain')
        ->toContain('bg-glass-effect')
        ->not->toContain('listen-again-backdrop')
        ->and($show)
        ->toContain('<GameplayChatCard');
});
