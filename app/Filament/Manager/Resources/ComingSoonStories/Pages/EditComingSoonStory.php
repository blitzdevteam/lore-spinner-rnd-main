<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\ComingSoonStories\Pages;

use App\Filament\Manager\Resources\ComingSoonStories\ComingSoonStoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditComingSoonStory extends EditRecord
{
    protected static string $resource = ComingSoonStoryResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
