<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Users\Pages;

use App\Actions\Investor\RegisterInvestorAction;
use App\Enums\UserType;
use App\Filament\Manager\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

final class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static bool $canCreateAnother = false;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        if (UserType::isInvestor($data['type'] ?? null)) {
            return app(RegisterInvestorAction::class)->handle($data);
        }

        $data['type'] = UserType::User;

        return parent::handleRecordCreation($data);
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        $record = $this->getRecord();

        if ($record instanceof User && $record->isInvestor()) {
            return UserResource::getUrl('edit', ['record' => $record]);
        }

        return parent::getRedirectUrl();
    }

    #[Override]
    protected function getCreatedNotificationTitle(): ?string
    {
        $record = $this->getRecord();

        if ($record instanceof User && $record->isInvestor()) {
            return 'Investor registered. Copy the magic login link and send it to them.';
        }

        return parent::getCreatedNotificationTitle();
    }
}
