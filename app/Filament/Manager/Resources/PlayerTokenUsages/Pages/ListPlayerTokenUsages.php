<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerTokenUsages\Pages;

use App\Filament\Manager\Resources\PlayerTokenUsages\PlayerTokenUsageResource;
use Filament\Resources\Pages\ListRecords;

final class ListPlayerTokenUsages extends ListRecords
{
    protected static string $resource = PlayerTokenUsageResource::class;

    public function getSubheading(): string
    {
        return 'Player gameplay requests only. Table totals update with the active filters.';
    }
}
