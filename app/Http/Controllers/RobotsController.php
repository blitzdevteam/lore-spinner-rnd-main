<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Sitemap;
use Illuminate\Http\Response;

final class RobotsController extends Controller
{
    /**
     * Paths that are private, experimental, or not useful in search results.
     *
     * @var list<string>
     */
    private const DISALLOWED_PATHS = [
        '/user/',
        '/writer/',
        '/creator/',
        '/manager/',
        '/filament/',
        '/multiplayer/',
        '/investor-access/',
        '/chaos-mode',
        '/xen-rnd',
        '/design-rnd',
        '/bg-rnd',
        '/old-homepage',
        '/expansion-status',
        '/feedback/',
    ];

    public function __invoke(Sitemap $sitemap): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
        ];

        foreach (self::DISALLOWED_PATHS as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.$sitemap->url('sitemap');

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
