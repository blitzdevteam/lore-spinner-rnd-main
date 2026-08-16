<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\ComingSoonStories\Pages;

use App\Filament\Manager\Resources\ComingSoonStories\ComingSoonStoryResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateComingSoonStory extends CreateRecord
{
    protected static string $resource = ComingSoonStoryResource::class;

    protected static bool $canCreateAnother = false;
}
