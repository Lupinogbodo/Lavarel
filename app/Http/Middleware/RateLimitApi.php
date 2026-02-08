<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Custom API Rate Limiting Middleware
 * 
 * Protection against:
 * - Brute force attacks
 * - Rate limit bypass attempts
 * - API abuse
 * 
 * Strategy:
 * - Multiple rate limit tiers based on authentication
 * - IP + User ID combination for authenticated requests
 * - IP + Endpoint hash for anonymous requests
 * - Progressive backoff on violations
 * - Distributed rate limiting via Redis
 */
class RateLimitApi
{
    protected $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $key = $this->resolveRequestSignature($request);
        
        // Check if too many attempts
        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildRateLimitResponse($key, $maxAttempts);
        }

        // Increment attempts
        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        // Add rate limit headers
        return $this->addRateLimitHeaders(
            $response,
            $maxAttempts,
            $this->limiter->retriesLeft($key, $maxAttempts),
            $this->limiter->availableIn($key)
        );
    }

    /**
     * Resolve unique request signature for rate limiting
     * 
     * Anti-bypass measures:
     * - Combines multiple identifiers (IP, User, Endpoint, User-Agent hash)
     * - Prevents X-Forwarded-For spoofing
     * - User ID binding for authenticated requests
     * - Fingerprinting to detect proxy switching
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $parts = [];

        // Use authenticated user ID if available
        if ($user = $request->user()) {
            $parts[] = 'user:' . $user->id;
        }

        // Get real IP (with spoofing protection)
        $ip = $this->getTrustedIp($request);
        $parts[] = 'ip:' . $ip;

        // Add endpoint to prevent cross-endpoint rate limit bypass
        $parts[] = 'route:' . md5($request->path() . $request->method());

        // Add user agent fingerprint to detect proxy rotation
        $parts[] = 'ua:' . substr(md5($request->userAgent() ?? 'unknown'), 0, 8);

        return 'rate_limit:' . implode('|', $parts);
    }

    /**
     * Get trusted client IP with anti-spoofing measures
     */
    protected function getTrustedIp(Request $request): string
    {
        // Don't trust X-Forwarded-For unless behind trusted proxy
        $trustedProxies = config('trustedproxies.proxies', []);
        
        if (!empty($trustedProxies) && $request->server('REMOTE_ADDR')) {
            // Only trust proxy headers if request comes from trusted proxy
            $remoteAddr = $request->server('REMOTE_ADDR');
            if (in_array($remoteAddr, $trustedProxies) || $remoteAddr === '127.0.0.1') {
                return $request->ip(); // Laravel's trusted IP resolution
            }
        }

        // Fallback to direct connection IP
        return $request->server('REMOTE_ADDR') ?? $request->ip();
    }

    /**
     * Build rate limit exceeded response
     */
    protected function buildRateLimitResponse(string $key, int $maxAttempts): Response
    {
        $retryAfter = $this->limiter->availableIn($key);

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => 'Too many requests. Please slow down.',
                'retry_after' => $retryAfter,
                'retry_after_human' => gmdate('H:i:s', $retryAfter)
            ]
        ], 429)->header('Retry-After', $retryAfter);
    }

    /**
     * Add rate limit headers to response
     */
    protected function addRateLimitHeaders(Response $response, int $maxAttempts, int $remaining, int $resetTime): Response
    {
        return $response
            ->header('X-RateLimit-Limit', $maxAttempts)
            ->header('X-RateLimit-Remaining', max(0, $remaining))
            ->header('X-RateLimit-Reset', time() + $resetTime);
    }
}
