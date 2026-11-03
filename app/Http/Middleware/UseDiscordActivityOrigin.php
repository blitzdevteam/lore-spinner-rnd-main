<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Controllers\Discord\ActivityController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Discord only lets an activity iframe reach its own discordsays.com origin,
 * so redirects and asset URLs must point there instead of the real host.
 */
final class UseDiscordActivityOrigin
{
    public static function origin(): ?string
    {
        $configured = (string) config('discord.activity_origin');

        if ($configured !== '') {
            return mb_rtrim($configured, '/');
        }

        $applicationId = (string) config('discord.application_id');

        return $applicationId === '' ? null : "https://{$applicationId}.discordsays.com";
    }

    public function handle(Request $request, Closure $next): Response
    {
        $origin = self::origin();

        if ($origin !== null && $request->hasSession() && $request->session()->has(ActivityController::SESSION_INSTANCE_KEY)) {
            URL::forceRootUrl($origin);
            URL::forceScheme('https');
        }

        return $next($request);
    }
}
