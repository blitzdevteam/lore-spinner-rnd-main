<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class PublicMedia
{
    /**
     * Collections that the public site is allowed to display.
     *
     * @var list<string>
     */
    public const COLLECTIONS = [
        'avatar',
        'background',
        'banner',
        'cover',
        'gallery',
        'outro',
    ];

    public static function isPublic(Media $media): bool
    {
        return in_array($media->collection_name, self::COLLECTIONS, true);
    }

    public static function url(Media $media, ?string $conversion = null): string
    {
        return route('media.show', [
            'media' => $media,
            ...filled($conversion) ? ['conversion' => $conversion] : [],
        ]);
    }
}
