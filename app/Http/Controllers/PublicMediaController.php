<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\PublicMedia;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicMediaController extends Controller
{
    public function __invoke(Request $request, Media $media): StreamedResponse
    {
        abort_unless(PublicMedia::isPublic($media), 404);

        $conversion = $request->string('conversion')->toString();

        if ($conversion !== '' && $media->hasGeneratedConversion($conversion)) {
            return $media->toInlineResponse($request, $conversion);
        }

        return $media->toInlineResponse($request);
    }
}
