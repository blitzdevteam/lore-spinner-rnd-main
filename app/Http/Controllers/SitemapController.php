<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Sitemap;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    public function __invoke(Sitemap $sitemap): Response
    {
        return response($sitemap->toXml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
