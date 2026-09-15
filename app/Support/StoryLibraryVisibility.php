<?php

declare(strict_types=1);

namespace App\Support;

final class StoryLibraryVisibility
{
    public static function isHidden(string $slug): bool
    {
        /** @var list<string> $hidden */
        $hidden = config('stories.library_hidden_slugs', []);

        return in_array($slug, $hidden, true);
    }
}
