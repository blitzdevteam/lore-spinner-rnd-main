<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumToArray;
use Filament\Support\Contracts\HasLabel;

enum UserType: string implements HasLabel
{
    use EnumToArray;

    case User = 'user';
    case Investor = 'investor';

    public static function isInvestor(mixed $type): bool
    {
        if ($type instanceof self) {
            return $type === self::Investor;
        }

        if (! is_string($type)) {
            return false;
        }

        return self::tryFrom($type) === self::Investor;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Investor => 'Investor',
        };
    }
}
