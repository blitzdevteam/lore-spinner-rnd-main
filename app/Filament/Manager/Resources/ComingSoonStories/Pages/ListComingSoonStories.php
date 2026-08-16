<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\ComingSoonStories\Pages;

use App\Filament\Manager\Resources\ComingSoonStories\ComingSoonStoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListComingSoonStories extends ListRecords
{
    protected static string $resource = ComingSoonStoryResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
