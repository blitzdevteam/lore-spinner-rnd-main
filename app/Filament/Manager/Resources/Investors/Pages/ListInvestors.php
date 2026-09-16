<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Investors\Pages;

use App\Filament\Manager\Resources\Investors\InvestorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListInvestors extends ListRecords
{
    protected static string $resource = InvestorResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Register investor'),
        ];
    }
}
