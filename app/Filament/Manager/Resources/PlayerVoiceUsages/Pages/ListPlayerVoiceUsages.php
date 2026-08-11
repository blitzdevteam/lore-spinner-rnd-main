<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerVoiceUsages\Pages;

use App\Filament\Manager\Resources\PlayerVoiceUsages\PlayerVoiceUsageResource;
use Filament\Resources\Pages\ListRecords;

final class ListPlayerVoiceUsages extends ListRecords
{
    protected static string $resource = PlayerVoiceUsageResource::class;

    public function getSubheading(): string
    {
        return 'Player narration audio only. Billable units, public PAYG estimates, and provider comparisons all follow the filters below.';
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
        return PlayerVoiceUsageResource::getWidgets();
    }
}
