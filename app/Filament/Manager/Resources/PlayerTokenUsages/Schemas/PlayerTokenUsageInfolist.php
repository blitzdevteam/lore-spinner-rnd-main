<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\PlayerTokenUsages\Schemas;

use App\Models\PlayerTokenUsage;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

final class PlayerTokenUsageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                View::make('filament.manager.resources.player-token-usages.usage-detail')
                    ->viewData(static fn (PlayerTokenUsage $record): array => [
                        'usage' => $record,
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
