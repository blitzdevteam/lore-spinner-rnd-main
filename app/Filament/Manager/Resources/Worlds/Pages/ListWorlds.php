<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Worlds\Pages;

use App\Filament\Manager\Resources\Worlds\WorldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListWorlds extends ListRecords
{
    protected static string $resource = WorldResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
