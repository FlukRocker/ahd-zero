<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * During the Inertia→Blade migration the two render styles coexist: the public
 * site is server-rendered Blade, while a few gated pages (member auth, admin,
 * settings) are still Inertia/Vue. When a link on an Inertia page points at a
 * Blade page, Inertia XHRs it (X-Inertia request header), receives plain HTML
 * with no X-Inertia response header, and shows that HTML in a modal popup
 * instead of navigating.
 *
 * This middleware detects exactly that case — an Inertia visit that resolved to
 * a non-Inertia (Blade) response — and replies with 409 + X-Inertia-Location,
 * which is Inertia's built-in signal to perform a full-page visit. So clicking
 * a nav/footer/search link from an Inertia page lands on the Blade page
 * normally, no popup.
 */
class ForceFullVisitToBladePages
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $isInertiaVisit = $request->header('X-Inertia') !== null;
        $isInertiaResponse = $response->headers->has('X-Inertia');

        // GET-only: POST/PUT/DELETE Inertia actions (login, logout, etc.) always
        // hit Inertia controllers and must not be rewritten.
        if (
            $isInertiaVisit
            && ! $isInertiaResponse
            && $request->isMethodSafe()
            && $response->getStatusCode() === 200
        ) {
            return response('', Response::HTTP_CONFLICT)
                ->header('X-Inertia-Location', $request->fullUrl());
        }

        return $response;
    }
}
