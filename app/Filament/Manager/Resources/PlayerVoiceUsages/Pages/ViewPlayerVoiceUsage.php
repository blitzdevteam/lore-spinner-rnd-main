<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerVoiceUsages\Pages;

use App\Filament\Manager\Resources\PlayerVoiceUsages\PlayerVoiceUsageResource;
use App\Models\PlayerVoiceUsage;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use LogicException;

final class ViewPlayerVoiceUsage extends ViewRecord
{
    protected static string $resource = PlayerVoiceUsageResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public function getTitle(): string
    {
        return 'Voice usage details';
    }

    public function getBreadcrumb(): string
    {
        return 'Details';
    }

    public function getRecordTitle(): string
    {
        return 'Generation #'.Str::upper(Str::substr(
            $this->voiceUsage()->prompt_reference_id,
            0,
            8,
        ));
    }

    public function getSubheading(): string
    {
        $usage = $this->voiceUsage();

        return implode(' · ', [
            $usage->story->title,
            $usage->user->email,
            $this->providerLabel($usage->provider).' / '.$usage->model,
            $usage->occurred_at->format('M j, Y H:i:s'),
        ]);
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to voice usage')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(PlayerVoiceUsageResource::getUrl('index')),
        ];
    }

    private function voiceUsage(): PlayerVoiceUsage
    {
        $record = $this->getRecord();

        if (! $record instanceof PlayerVoiceUsage) {
            throw new LogicException('Unexpected voice usage record type.');
        }

        return $record;
    }

    private function providerLabel(string $provider): string
    {
        return match (mb_strtolower($provider)) {
            'elevenlabs' => 'ElevenLabs',
            'deepgram' => 'Deepgram',
            default => Str::headline($provider),
        };
    }
}
