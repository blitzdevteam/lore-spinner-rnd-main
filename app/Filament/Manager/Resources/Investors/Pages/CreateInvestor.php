<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Investors\Pages;

use App\Actions\Investor\RegisterInvestorAction;
use App\Filament\Manager\Resources\Investors\InvestorResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

final class CreateInvestor extends CreateRecord
{
    protected static string $resource = InvestorResource::class;

    protected static ?string $title = 'Register investor';

    protected static bool $canCreateAnother = false;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        return app(RegisterInvestorAction::class)->handle($data);
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        $record = $this->getRecord();

        if (! $record instanceof User) {
            return parent::getRedirectUrl();
        }

        return InvestorResource::getUrl('edit', ['record' => $record]);
    }

    #[Override]
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Investor registered. Copy the magic login link and send it to them.';
    }

    #[Override]
    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Register investor');
    }
}
