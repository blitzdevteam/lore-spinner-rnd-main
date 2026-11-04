<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The activity runs in a third-party iframe on discordsays.com, so its session
 * cookie must be cross-site and host-only, and its pages must allow Discord to
 * frame them. The frame-ancestors policy also overrides the X-Frame-Options
 * header the hosting platform adds. The website keeps its own settings.
 */
final class PrepareDiscordActivityRequest
{
    public const string FRAME_ANCESTORS = "frame-ancestors 'self' https://discord.com https://*.discord.com https://*.discordsays.com https://*.discordapp.com";

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::comesFromDiscordActivity($request)) {
            return $next($request);
        }

        config([
            'session.same_site' => 'none',
            'session.secure' => true,
            'session.partitioned' => true,
            'session.domain' => null,
        ]);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', self::FRAME_ANCESTORS);
        $response->headers->remove('X-Frame-Options');
        $this->keepRedirectInsideActivity($response);

        return $response;
    }

    /**
     * An absolute redirect to discordsays.com makes Discord's proxy call itself and Cloudflare returns a 520.
     */
    private function keepRedirectInsideActivity(Response $response): void
    {
        $location = $response->headers->get('Location');

        if (! is_string($location) || ! str_starts_with($location, 'http')) {
            return;
        }

        $parts = parse_url($location);
        $host = $parts['host'] ?? null;

        if (! is_string($host) || ! $this->isActivityHost($host)) {
            return;
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        $response->headers->set('Location', $path.$query);
    }

    private function isActivityHost(string $host): bool
    {
        if (str_ends_with($host, '.discordsays.com')) {
            return true;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($appHost) && strcasecmp($appHost, $host) === 0;
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
