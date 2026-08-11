<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerVoiceUsages\Schemas;

use App\Models\PlayerVoiceUsage;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

final class PlayerVoiceUsageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                View::make('filament.manager.resources.player-voice-usages.usage-detail')
                    ->columnSpanFull()
                    ->viewData(static fn (PlayerVoiceUsage $record): array => [
                        'usage' => $record,
                    ]),
            ]);
    }
}
