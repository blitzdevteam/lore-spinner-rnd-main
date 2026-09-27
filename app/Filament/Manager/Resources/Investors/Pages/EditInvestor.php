<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Investors\Pages;

use App\Actions\Investor\SyncInvestorPlanAccessAction;
use App\Actions\Investor\UpdateInvestorAccessAction;
use App\Filament\Manager\Resources\Investors\InvestorResource;
use App\Models\User;
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
                ->label('Block test access')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Block test access?')
                ->modalDescription('This investor will need a paid subscription to keep playing stories.')
                ->successNotificationTitle('Test access blocked')
                ->visible(fn (): bool => $this->record instanceof User && $this->record->access_enabled)
                ->action(function (): void {
                    $record = $this->getInvestor();
                    $this->record = app(UpdateInvestorAccessAction::class)->block($record);
                }),
            Action::make('restoreAccess')
                ->label('Restore test access')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Restore test access?')
                ->modalDescription('This investor will receive a new access window on the assigned plan.')
                ->successNotificationTitle('Test access restored')
                ->visible(fn (): bool => $this->record instanceof User && ! $this->record->hasActiveAccess())
                ->action(function (): void {
                    $record = $this->getInvestor();
                    $this->record = app(UpdateInvestorAccessAction::class)->restore($record);
                }),
            DeleteAction::make()
                ->modalDescription('Removes this investor account and test access.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $investor = $this->getInvestor();

        return [
            ...$data,
            'email' => $investor->hasCompletedSetup() ? $investor->email : null,
            'magic_login_url' => $investor->magicLoginUrl(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $investor = $record instanceof User ? $record : $this->getInvestor();
        $durationMonths = User::normalizeDurationMonths((int) $data['duration_months']);
        $planId = (int) $data['subscription_plan_id'];
        $planChanged = (int) $investor->subscription_plan_id !== $planId;
        $durationChanged = (int) $investor->duration_months !== $durationMonths;

        $payload = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'notes' => $data['notes'] ?? null,
            'subscription_plan_id' => $planId,
            'duration_months' => $durationMonths,
        ];

        if ($investor->access_enabled && $durationChanged) {
            $grantedAt = $investor->access_granted_at ?? now();
            $payload['expires_at'] = User::accessExpiresAt($durationMonths, $grantedAt);
        }

        $investor->update($payload);

        if ($investor->access_enabled && ($planChanged || $durationChanged)) {
            app(SyncInvestorPlanAccessAction::class)->grant($investor->refresh());
        }

        return $investor->refresh()->load('plan');
    }

    private function getInvestor(): User
    {
        $record = $this->getRecord();

        if (! $record instanceof User || ! $record->isInvestor()) {
            throw new RuntimeException('The current record is not an investor.');
        }

        return $record;
    }
}
