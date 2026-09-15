<?php

declare(strict_types=1);

it('keeps narration active when Inertia reloads the current game', function (): void {
    $showPage = file_get_contents(resource_path('js/pages/User/Games/Show.vue'));

    expect($showPage)
        ->toContain('new URL(raw, window.location.origin).pathname')
        ->toContain('const gamePath = `/user/games/${props.game.id}`;')
        ->toContain('pathname !== gamePath && !pathname.startsWith(`${gamePath}/`)')
        ->toContain('if (!raw) {')
        ->toContain('return false;');
});
