<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function str_starts_with;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), browsing-topics=()');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->remove('X-XSS-Protection');

        $csp = "default-src 'self'; "
            ."img-src 'self' data: blob: https: http:; "
            ."style-src 'self' 'unsafe-inline'; "
            ."font-src 'self' data:; "
            // The ad tags in the layout load from hosts their networks build at
            // runtime — the abscloud loader holds only 'https://' and a path,
            // and assembles the rest — so no list of names can cover them, and
            // an allowlist that has to be patched after every complaint is not
            // protecting anything either. Take the scheme, which is what
            // img-src, frame-src and media-src already do. 'unsafe-inline' and
            // 'unsafe-eval' are both already here, so the host list was not the
            // thing holding XSS back. Everything that does hold is below:
            // object-src none, base-uri self, form-action self, style-src self.
            ."script-src 'self' 'unsafe-inline' 'unsafe-eval' https: blob:; "
            ."connect-src 'self' https: wss: ws:; "
            .'frame-src https: http:; '
            ."media-src 'self' https: http: blob:; "
            ."object-src 'none'; "
            ."base-uri 'self'; "
            ."form-action 'self'";
        $response->headers->set('Content-Security-Policy', $csp);

        $isGuest = ! $request->user() && ! $request->user('member');
        $isGet = $request->isMethod('GET') || $request->isMethod('HEAD');
        $path = $request->path();
        $skip = str_starts_with($path, 'dashboard')
            || str_starts_with($path, 'settings')
            || str_starts_with($path, 'api/')
            || str_starts_with($path, 'login')
            || str_starts_with($path, 'member/')
            || str_starts_with($path, 'two-factor');

        if ($isGuest && $isGet && ! $skip) {
            $response->headers->set('Cache-Control', 'public, max-age=60, s-maxage=300');
            $response->headers->remove('Pragma');
        }

        return $response;
    }
}
