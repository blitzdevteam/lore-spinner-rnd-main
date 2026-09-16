<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Investors\Pages;

use App\Actions\Investor\RegisterInvestorAction;
use App\Filament\Manager\Resources\Investors\InvestorResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

final class CreateInvestor extends CreateRecord
{
    protected static string $resource = InvestorResource::class;

    protected static bool $canCreateAnother = false;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        return app(RegisterInvestorAction::class)->handle($data);
    }
}
