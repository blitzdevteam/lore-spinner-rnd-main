<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('starts the opening narration request when the game page mounts', function () {
    $page = File::get(resource_path('js/pages/User/Games/Show.vue'));

    // The narration is the long pole of the cinematic. It must be requested on mount,
    // not when the showcard appears, or a skip only makes the player wait later.
    expect($page)->toContain('} else if (showCinematic.value) {')
        ->and($page)->toContain('let beginRequested = false;')
        ->and($page)->toContain('if (beginRequested) return;');
});

it('renders the intro skip hint in bold white', function () {
    $component = File::get(resource_path('js/components/GameCinematicOpening.vue'));

    expect($component)->toContain('class="co-skip-hint">tap to skip</p>')
        ->and($component)->toContain('font-weight: 700;')
        ->and($component)->toContain('color: #fff;')
        ->and($component)->not->toContain('rgba(255, 255, 255, 0.18)');
});

it('lets a skip shorten the showcard hold', function () {
    $component = File::get(resource_path('js/components/GameCinematicOpening.vue'));

    expect($component)->toContain('SHOWCARD_SKIP_MS')
        ->and($component)->toContain('FADE_OUT_SKIP_MS')
        ->and($component)->toContain('skipped.value ? SHOWCARD_SKIP_MS : SHOWCARD_MIN_MS')
        ->and($component)->toContain('skipped.value ? FADE_OUT_SKIP_MS : FADE_OUT_MS');

    $skipMs = (int) str($component)->after('const SHOWCARD_SKIP_MS = ')->before(';')->toString();
    $minMs = (int) str($component)->after('const SHOWCARD_MIN_MS = ')->before(';')->toString();

    expect($skipMs)->toBeGreaterThan(0)->toBeLessThan($minMs);
});

it('still waits for the opening to land before leaving the cinematic', function () {
    $component = File::get(resource_path('js/components/GameCinematicOpening.vue'));

    // Skipping trims the dwell, never the guard: there is no game to show until
    // the parent reports the opening narration has settled.
    expect($component)->toContain('if (!minDwellReached.value || !props.canExit) return;');
});
