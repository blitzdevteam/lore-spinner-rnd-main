<?php

declare(strict_types=1);

namespace App\Enums\Story;

use App\Traits\EnumToArray;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum StoryRatingEnum: string implements HasLabel
{
    use EnumToArray;

    case EVERYONE = 'everyone';
    case TEEN = 'teen';
    case YOUNG_ADULT = 'young-adult';
    case MATURE = 'mature';

    public function getLabel(): string
    {
        return match ($this) {
            self::EVERYONE => 'Everyone',
            self::TEEN => 'Teen (13+)',
            self::YOUNG_ADULT => 'Young Adult (16+)',
            self::MATURE => 'Mature (18+)',
        };
    }

    public function getSeverity(): string
    {
        return match ($this) {
            self::EVERYONE => 'success',
            self::TEEN => 'info',
            self::YOUNG_ADULT => 'warning',
            self::MATURE => 'danger',
        };
    }
}
