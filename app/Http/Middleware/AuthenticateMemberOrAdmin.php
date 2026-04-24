<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow access if authenticated as member OR as admin (web guard).
 */
class AuthenticateMemberOrAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('member')->check() || Auth::guard('web')->check()) {
            return $next($request);
        }

        return redirect()->route('member.login');
    }
}
