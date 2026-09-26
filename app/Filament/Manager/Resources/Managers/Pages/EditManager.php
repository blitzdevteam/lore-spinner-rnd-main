<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Managers\Pages;

use App\Filament\Manager\Resources\Managers\ManagerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditManager extends EditRecord
{
    protected static string $resource = ManagerResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
