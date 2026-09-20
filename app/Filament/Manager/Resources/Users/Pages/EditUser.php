<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Users\Pages;

use App\Actions\Investor\SyncInvestorPlanAccessAction;
use App\Actions\Investor\UpdateInvestorAccessAction;
use App\Filament\Manager\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

final class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('blockAccess')
                ->label('Block test access')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Block test access?')
                ->modalDescription('This investor will need a paid subscription to keep playing stories.')
                ->successNotificationTitle('Test access blocked')
                ->visible(fn (): bool => $this->record instanceof User && $this->record->isInvestor() && $this->record->access_enabled)
                ->action(function (): void {
                    $record = $this->getRecord();

                    if ($record instanceof User) {
                        $this->record = app(UpdateInvestorAccessAction::class)->block($record);
                    }
                }),
            Action::make('restoreAccess')
                ->label('Restore test access')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Restore test access?')
                ->modalDescription('This investor will receive a new access window on the assigned plan.')
                ->successNotificationTitle('Test access restored')
                ->visible(fn (): bool => $this->record instanceof User && $this->record->isInvestor() && ! $this->record->hasActiveAccess())
                ->action(function (): void {
                    $record = $this->getRecord();

                    if ($record instanceof User) {
                        $this->record = app(UpdateInvestorAccessAction::class)->restore($record);
                    }
                }),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if (! $record instanceof User || ! $record->isInvestor()) {
            return $data;
        }

        return [
            ...$data,
            'email' => $record->hasCompletedSetup() ? $record->email : null,
            'magic_login_url' => $record->magicLoginUrl(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof User || ! $record->isInvestor()) {
            unset($data['type'], $data['subscription_plan_id'], $data['duration_months'], $data['notes'], $data['magic_login_url']);

            return parent::handleRecordUpdate($record, $data);
        }

        $durationMonths = max(1, (int) $data['duration_months']);
        $planId = (int) $data['subscription_plan_id'];
        $planChanged = (int) $record->subscription_plan_id !== $planId;
        $durationChanged = (int) $record->duration_months !== $durationMonths;

        $payload = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'notes' => $data['notes'] ?? null,
            'subscription_plan_id' => $planId,
            'duration_months' => $durationMonths,
        ];

        if ($record->access_enabled && $durationChanged) {
            $grantedAt = $record->access_granted_at ?? now();
            $payload['expires_at'] = $grantedAt->copy()->addMonthsNoOverflow($durationMonths);
        }

        $record->update($payload);

        if ($record->access_enabled && ($planChanged || $durationChanged)) {
            app(SyncInvestorPlanAccessAction::class)->grant($record->refresh());
        }

        return $record->refresh()->load('plan');
    }
}
