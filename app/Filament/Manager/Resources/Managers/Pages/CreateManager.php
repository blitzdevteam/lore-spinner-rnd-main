<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Managers\Pages;

use App\Filament\Manager\Resources\Managers\ManagerResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateManager extends CreateRecord
{
    protected static string $resource = ManagerResource::class;
}
