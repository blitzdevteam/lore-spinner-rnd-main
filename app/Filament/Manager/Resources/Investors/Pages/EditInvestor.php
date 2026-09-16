<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Investors\Pages;

use App\Actions\Investor\UpdateInvestorAccessAction;
use App\Filament\Manager\Resources\Investors\InvestorResource;
use App\Models\Investor;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Override;
use RuntimeException;

final class EditInvestor extends EditRecord
{
    protected static string $resource = InvestorResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('blockAccess')
                ->label('Block free access')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Block complimentary access?')
                ->modalDescription('This investor will need a paid subscription to keep playing stories.')
                ->successNotificationTitle('Complimentary access blocked')
                ->visible(fn (): bool => $this->record instanceof Investor && $this->record->access_enabled)
                ->action(function (): void {
                    $record = $this->getInvestor();
                    $this->record = app(UpdateInvestorAccessAction::class)->block($record);
                }),
            Action::make('restoreAccess')
                ->label('Restore free access')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Restore complimentary access?')
                ->modalDescription('This investor will be able to play stories again without a paid plan.')
                ->successNotificationTitle('Complimentary access restored')
                ->visible(fn (): bool => $this->record instanceof Investor && ! $this->record->access_enabled)
                ->action(function (): void {
                    $record = $this->getInvestor();
                    $this->record = app(UpdateInvestorAccessAction::class)->restore($record);
                }),
            DeleteAction::make()
                ->modalDescription('Removes this investor record and complimentary access. The player account stays in Users.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->getInvestor()->user;

        return [
            ...$data,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'email' => $user->email,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $investor = $record instanceof Investor ? $record : $this->getInvestor();
        $userPayload = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'username' => $data['username'],
            'email' => $data['email'],
        ];

        if (filled($data['password'] ?? null)) {
            $userPayload['password'] = $data['password'];
        }

        $investor->user->update($userPayload);
        $investor->update([
            'notes' => $data['notes'] ?? null,
        ]);

        return $investor->refresh()->load('user');
    }

    private function getInvestor(): Investor
    {
        $record = $this->getRecord();

        if (! $record instanceof Investor) {
            throw new RuntimeException('The current record is not an investor.');
        }

        return $record;
    }
}
