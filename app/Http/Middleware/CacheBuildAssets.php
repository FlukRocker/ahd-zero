<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function str_starts_with;

/**
 * Vite emits hashed filenames under /build/* — content-addressed assets that
 * never change once published. PSI flagged 12h cache TTLs as wasteful: every
 * repeat visitor revalidates 19 chunks every 12h. Set 1 year + immutable so
 * the browser's first cache hit is also its last fetch.
 *
 * Serves the same 1y header for /favicon.png, /apple-touch-icon.png, and
 * /robots.txt's siblings — these get re-deployed manually on a release, not
 * per-request, so a stale entry is fine.
 */
class CacheBuildAssets
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $path = $request->path();

        if (str_starts_with($path, 'build/')) {
            $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');

            return $response;
        }

        // Hashed Vite SSR helpers occasionally bleed through; same treatment.
        if (str_starts_with($path, 'storage/') && $request->isMethod('GET')) {
            $response->headers->set('Cache-Control', 'public, max-age=31536000');
        }

        return $response;
    }
}
