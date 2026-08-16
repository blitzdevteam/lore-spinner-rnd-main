<?php

declare(strict_types=1);

namespace App\Enums\ComingSoonStory;

use App\Traits\EnumToArray;
use Filament\Support\Contracts\HasLabel;

enum ComingSoonStorySectionEnum: string implements HasLabel
{
    use EnumToArray;

    case HERO = 'hero';
    case FEATURED_WORLDS = 'featured-worlds';
    case NEW_STORIES = 'new-stories';
    case COMING_SOON = 'coming-soon';

    public function getLabel(): string
    {
        return match ($this) {
            self::HERO => 'Hero',
            self::FEATURED_WORLDS => 'Featured Worlds',
            self::NEW_STORIES => 'New Stories',
            self::COMING_SOON => 'Coming Soon',
        };
    }
}
