<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Managers\Pages;

use App\Filament\Manager\Resources\Managers\ManagerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListManagers extends ListRecords
{
    protected static string $resource = ManagerResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
