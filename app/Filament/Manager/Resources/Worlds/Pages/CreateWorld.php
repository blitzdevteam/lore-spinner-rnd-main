<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Worlds\Pages;

use App\Filament\Manager\Resources\Worlds\WorldResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateWorld extends CreateRecord
{
    protected static string $resource = WorldResource::class;

    protected static bool $canCreateAnother = false;
}
