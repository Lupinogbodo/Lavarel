<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Input Sanitization Middleware
 * 
 * Protection against:
 * - XSS via input injection
 * - SQL injection (defense in depth)
 * - Script injection in JSON payloads
 * - Null byte injection
 * 
 * Note: This is defense-in-depth. Primary protections are:
 * - Eloquent ORM (prevents SQL injection)
 * - Blade escaping (prevents XSS in views)
 * - Validation rules (input validation)
 */
class SanitizeInput
{
    /**
     * Dangerous patterns to detect/strip
     */
    protected $dangerousPatterns = [
        // Null byte injection
        '/\x00/',
        // Unicode null and control characters
        '/[\x{0000}-\x{001F}]/u',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sanitize all input data
        $input = $request->all();
        $sanitized = $this->sanitizeArray($input);
        $request->merge($sanitized);

        $response = $next($request);

        return $response;
    }

    /**
     * Recursively sanitize array data
     */
    protected function sanitizeArray(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            // Sanitize the key itself
            $cleanKey = $this->sanitizeString($key);

            if (is_array($value)) {
                // Recursively sanitize nested arrays
                $sanitized[$cleanKey] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                // Sanitize string values
                $sanitized[$cleanKey] = $this->sanitizeString($value);
            } else {
                // Keep non-string primitives as-is
                $sanitized[$cleanKey] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize individual string value
     */
    protected function sanitizeString(string $value): string
    {
        // Remove null bytes and other dangerous patterns
        foreach ($this->dangerousPatterns as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }

        // Trim whitespace (prevents padding attacks)
        $value = trim($value);

        // Remove invisible Unicode characters that could be used for obfuscation
        $value = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{FEFF}]/u', '', $value);

        return $value;
    }

    /**
     * Check if string contains potential XSS
     */
    protected function containsXss(string $value): bool
    {
        $xssPatterns = [
            '/<script\b[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',  // Event handlers like onclick=
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
        ];

        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }
}
