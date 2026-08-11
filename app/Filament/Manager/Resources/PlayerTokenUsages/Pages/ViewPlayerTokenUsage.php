<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerTokenUsages\Pages;

use App\Filament\Manager\Resources\PlayerTokenUsages\PlayerTokenUsageResource;
use App\Models\PlayerTokenUsage;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use LogicException;

final class ViewPlayerTokenUsage extends ViewRecord
{
    protected static string $resource = PlayerTokenUsageResource::class;

    protected static ?string $title = 'Token usage details';

    protected Width|string|null $maxContentWidth = Width::Full;

    public function getBreadcrumb(): string
    {
        return 'Details';
    }

    public function getRecordTitle(): string
    {
        return 'Request #'.mb_strtoupper(mb_substr(
            $this->usage()->prompt_reference_id,
            0,
            8,
        ));
    }

    public function getSubheading(): string
    {
        $usage = $this->usage();

        return implode(' · ', [
            $usage->story->title,
            $usage->user->email,
            $usage->occurred_at->format('M j, Y \a\t H:i'),
        ]);
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToUsage')
                ->label('Back to token usage')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(PlayerTokenUsageResource::getUrl('index')),
        ];
    }

    private function usage(): PlayerTokenUsage
    {
        $record = $this->getRecord();

        if (! $record instanceof PlayerTokenUsage) {
            throw new LogicException('Unexpected token usage record type.');
        }

        return $record;
    }
}
