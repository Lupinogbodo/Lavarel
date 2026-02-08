<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security Headers Middleware
 * 
 * Protection against:
 * - XSS (Cross-Site Scripting)
 * - Clickjacking
 * - MIME-type sniffing
 * - Referrer leakage
 * - Mixed content attacks
 * 
 * Implements OWASP recommendations for HTTP security headers
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Relaxed CSP for local development
        $cspDirectives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self' http://localhost:* http://127.0.0.1:*",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        // Only upgrade insecure requests in production
        if (app()->environment('production')) {
            $cspDirectives[] = "upgrade-insecure-requests";
        }

        // Content Security Policy - Primary XSS defense
        // Restricts sources for scripts, styles, images, etc.
        $response->headers->set(
            'Content-Security-Policy',
            implode('; ', $cspDirectives)
        );

        // X-Content-Type-Options - Prevent MIME sniffing
        // Stops browsers from interpreting files as different MIME type
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // X-Frame-Options - Clickjacking protection
        // Prevents page from being embedded in iframe
        $response->headers->set('X-Frame-Options', 'DENY');

        // X-XSS-Protection - Legacy XSS filter (for older browsers)
        // Modern browsers use CSP, but this helps older ones
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Strict-Transport-Security - Force HTTPS
        // Tells browser to only connect via HTTPS for next 1 year
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Referrer-Policy - Control referrer information
        // Prevents leaking sensitive URLs to external sites
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions-Policy - Control browser features
        // Disable potentially dangerous browser features
        $response->headers->set(
            'Permissions-Policy',
            implode(', ', [
                'geolocation=()',        // Disable geolocation
                'microphone=()',         // Disable microphone
                'camera=()',             // Disable camera
                'payment=()',            // Disable payment API
                'usb=()',                // Disable USB
                'magnetometer=()',       // Disable magnetometer
                'gyroscope=()',          // Disable gyroscope
                'accelerometer=()'       // Disable accelerometer
            ])
        );

        // Remove server fingerprinting headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        // Set secure cache headers for API responses
        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
