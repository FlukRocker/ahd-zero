<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Keeps a route out of search engines even when a crawler fetches it directly. */
class NoIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request)->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
