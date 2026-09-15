<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\PublicMedia;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class SameOriginMediaPreview
{
    /**
     * @return array{name: string|null, size: int|null, type: string|null, url: string|null}|null
     */
    public static function uploadedFile(SpatieMediaLibraryFileUpload $component, string $file): ?array
    {
        $record = $component->getRecord();

        if ($record === null) {
            return null;
        }

        $mediaItems = $record->getRelationValue('media');

        if (! $mediaItems instanceof Collection) {
            return null;
        }

        /** @var ?Media $media */
        $media = $mediaItems->firstWhere('uuid', $file);

        if ($media === null) {
            return null;
        }

        $conversion = $component->getConversion();
        $usesConversion = filled($conversion) && $media->hasGeneratedConversion((string) $conversion);

        return [
            'name' => $media->getAttributeValue('name') ?? $media->getAttributeValue('file_name'),
            'size' => $media->getAttributeValue('size'),
            'type' => $media->getAttributeValue('mime_type'),
            'url' => self::url($media, $usesConversion ? (string) $conversion : null),
        ];
    }

    public static function url(Media $media, ?string $conversion = null): string
    {
        $uuid = PublicMedia::ensureUuid($media);

        return route('filament.media.preview', [
            'media' => $uuid,
            ...filled($conversion) ? ['conversion' => $conversion] : [],
        ]);
    }
}
