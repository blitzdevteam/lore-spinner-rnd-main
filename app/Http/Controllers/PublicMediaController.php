<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\PublicMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicMediaController extends Controller
{
    public function __invoke(Request $request, Media $media): StreamedResponse
    {
        abort_unless(PublicMedia::isPublic($media), 404);

        $conversion = $request->string('conversion')->toString();
        $useConversion = $conversion !== '' && $media->hasGeneratedConversion($conversion);
        $disk = $useConversion ? $media->conversions_disk : $media->disk;
        $path = $media->getPathRelativeToRoot($useConversion ? $conversion : '');

        abort_unless(Storage::disk($disk)->exists($path), 404);

        if ($useConversion) {
            return $media->toInlineResponse($request, $conversion);
        }

        return $media->toInlineResponse($request);
    }
}
