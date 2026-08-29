<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Worlds\Pages;

use App\Filament\Manager\Resources\Worlds\WorldResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditWorld extends EditRecord
{
    protected static string $resource = WorldResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
