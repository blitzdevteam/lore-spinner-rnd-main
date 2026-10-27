<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records one page_views row per (session, calendar day) on public marketing routes.
 *
 * Only fires on full-page GET requests — Inertia partial requests are skipped
 * via the X-Inertia header check so soft-navigations between pages in the same
 * session don't create additional rows.
 *
 * The unique constraint on (session_id, view_date) makes the upsert idempotent.
 * Discord Activity launches include frame_id and are not marketing page views.
 */
final class RecordPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $request->isMethod('GET')
            && $response->isSuccessful()
            && ! $request->header('X-Inertia')
            && ! $request->filled('frame_id')
        ) {
            DB::table('page_views')->upsert(
                [
                    'user_id' => $request->user('user')?->id,
                    'session_id' => $request->session()->getId(),
                    'path' => mb_substr($request->path(), 0, 512),
                    'view_date' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                uniqueBy: ['session_id', 'view_date'],
                update: ['updated_at' => now()],
            );
        }

        return $response;
    }
}
