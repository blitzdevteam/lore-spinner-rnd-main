<?php

declare(strict_types=1);

namespace App\Http\Controllers\Filament;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MediaPreviewController extends Controller
{
    public function __invoke(Request $request, Media $media): StreamedResponse
    {
        $conversion = $request->string('conversion')->toString();

        if ($conversion !== '' && $media->hasGeneratedConversion($conversion)) {
            return $media->toInlineResponse($request, $conversion);
        }

        return $media->toInlineResponse($request);
    }
}
