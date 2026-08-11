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
        return 'Player gameplay only. The cards, charts, and ledger totals all follow the filters below.';
    }

    /** @return array<string, int> */
    public function getHeaderWidgetsColumns(): array
    {
        return [
            'default' => 1,
            'xl' => 2,
        ];
    }

    /** @return array<class-string> */
    protected function getHeaderWidgets(): array
    {
        return PlayerTokenUsageResource::getWidgets();
    }
}
