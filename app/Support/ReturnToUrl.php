<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class ReturnToUrl
{
    public static function fromRequest(Request $request): ?string
    {
        $candidate = $request->input('return_to');

        if (! is_string($candidate) || $candidate === '') {
            $candidate = $request->headers->get('referer');
        }

        return self::normalize($candidate, $request);
    }

    private static function normalize(mixed $candidate, Request $request): ?string
    {
        if (! is_string($candidate) || $candidate === '' || preg_match('/[\x00-\x1F\x7F\\\\]/', $candidate) === 1) {
            return null;
        }

        $parts = parse_url($candidate);

        if ($parts === false) {
            return null;
        }

        if (isset($parts['host']) || isset($parts['scheme'])) {
            if (mb_strtolower((string) ($parts['host'] ?? '')) !== mb_strtolower($request->getHost())) {
                return null;
            }

            $candidate = ($parts['path'] ?? '/')
                . (isset($parts['query']) ? '?'.$parts['query'] : '')
                . (isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
        }

        if (! str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            return null;
        }

        return $candidate;
    }
}
