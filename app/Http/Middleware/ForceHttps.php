<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force HTTPS Middleware
 * 
 * Protection against:
 * - Man-in-the-middle (MITM) attacks
 * - Session hijacking
 * - Credential theft
 * - Data interception
 */
class ForceHttps
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Force HTTPS in production
        if (!$request->secure() && app()->environment('production')) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
