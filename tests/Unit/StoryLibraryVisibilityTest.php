<?php

declare(strict_types=1);

use App\Support\StoryLibraryVisibility;

it('hides off-platform and coming-soon titles from the library', function (): void {
    expect(StoryLibraryVisibility::isHidden('dream-police'))->toBeTrue()
        ->and(StoryLibraryVisibility::isHidden('the-matrix'))->toBeTrue()
        ->and(StoryLibraryVisibility::isHidden('momotaro-the-peach-boy'))->toBeTrue()
        ->and(StoryLibraryVisibility::isHidden('emma'))->toBeFalse();
});
