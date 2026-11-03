<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The activity runs in a third-party iframe on discordsays.com, so its session
 * cookie must be cross-site and host-only. The website keeps its own settings.
 */
final class ConfigureDiscordActivityCookies
{
    public function handle(Request $request, Closure $next): Response
    {
        if (self::comesFromDiscordActivity($request)) {
            config([
                'session.same_site' => 'none',
                'session.secure' => true,
                'session.partitioned' => true,
                'session.domain' => null,
            ]);
        }

        return $next($request);
    }

    public static function comesFromDiscordActivity(Request $request): bool
    {
        if ($request->filled('frame_id') && $request->filled('instance_id')) {
            return true;
        }

        foreach ([$request->headers->get('Origin'), $request->headers->get('Referer')] as $source) {
            $host = is_string($source) ? parse_url($source, PHP_URL_HOST) : null;

            if (is_string($host) && str_ends_with($host, '.discordsays.com')) {
                return true;
            }
        }

        return false;
    }
}
