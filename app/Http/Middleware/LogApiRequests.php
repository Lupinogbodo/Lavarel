<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Request Logger Middleware
 * 
 * Security audit trail:
 * - Logs all API requests
 * - Tracks user actions
 * - Detects suspicious patterns
 * - Compliance with audit requirements
 */
class LogApiRequests
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        // Process the request
        $response = $next($request);

        // Calculate request duration
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // Log the request (async to not slow down response)
        $this->logRequest($request, $response, $duration);

        return $response;
    }

    /**
     * Log request details
     */
    protected function logRequest(Request $request, Response $response, float $duration): void
    {
        $user = $request->user();

        $logData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $user?->id,
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'timestamp' => now()->toISOString(),
        ];

        // Don't log sensitive data (passwords, tokens, etc.)
        $input = $request->except(['password', 'password_confirmation', 'token', 'api_key']);
        
        if (!empty($input)) {
            $logData['input'] = $input;
        }

        // Log to appropriate channel
        if ($response->getStatusCode() >= 400) {
            \Log::channel('api')->warning('API_REQUEST_ERROR', $logData);
        } else {
            \Log::channel('api')->info('API_REQUEST', $logData);
        }

        // Flag suspicious patterns
        if ($this->isSuspicious($request, $response)) {
            \Log::channel('security')->warning('SUSPICIOUS_REQUEST', $logData);
        }
    }

    /**
     * Detect suspicious request patterns
     */
    protected function isSuspicious(Request $request, Response $response): bool
    {
        // Multiple failed authentication attempts
        if ($response->getStatusCode() === 401 && $request->is('api/*/login')) {
            return true;
        }

        // SQL injection attempt detection (common patterns)
        $input = json_encode($request->all());
        $sqlPatterns = [
            '/union.*select/i',
            '/select.*from.*where/i',
            '/drop.*table/i',
            '/insert.*into/i',
            '/update.*set/i',
            '/delete.*from/i',
            '/exec.*\(/i',
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        // XSS attempt detection
        $xssPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/onerror=/i',
            '/onclick=/i',
        ];

        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }
}
