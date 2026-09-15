<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Filament\Support\SameOriginMediaPreview;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Override;

final class MediaFileUpload extends SpatieMediaLibraryFileUpload
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->getUploadedFileUsing(SameOriginMediaPreview::uploadedFile(...));
    }
}
