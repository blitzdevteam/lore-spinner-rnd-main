<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;
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
        $uuid = self::ensureUuid($media);

        return route('media.show', [
            'media' => $uuid,
            ...filled($conversion) ? ['conversion' => $conversion] : [],
        ]);
    }

    /**
     * Older media rows can have a null uuid; Laravel then 500s while generating media.show.
     */
    public static function ensureUuid(Media $media): string
    {
        $uuid = $media->uuid;

        if (is_string($uuid) && $uuid !== '') {
            return $uuid;
        }

        $uuid = (string) Str::uuid();
        $media->uuid = $uuid;

        if ($media->exists) {
            $media->saveQuietly();
        }

        return $uuid;
    }
}
