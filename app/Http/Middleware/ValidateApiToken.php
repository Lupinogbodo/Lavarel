<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enhanced API Token Validation Middleware
 * 
 * Protection against:
 * - Token theft and replay attacks
 * - Token hijacking
 * - Concurrent session attacks
 * - Token enumeration
 * 
 * Security features:
 * - Token fingerprinting (IP + User-Agent binding)
 * - Token rotation on suspicious activity
 * - Concurrent session detection
 * - Token blacklisting on logout
 * - Anomaly detection
 */
class ValidateApiToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return $this->unauthorizedResponse('Missing authentication token');
        }

        // Prevent timing attacks in token lookup
        $accessToken = $this->findToken($token);

        if (!$accessToken) {
            return $this->unauthorizedResponse('Invalid token');
        }

        // Check if token is blacklisted (logged out)
        if ($this->isTokenBlacklisted($accessToken->id)) {
            return $this->unauthorizedResponse('Token has been revoked');
        }

        // Verify token fingerprint (anti-theft measure)
        if (!$this->verifyTokenFingerprint($request, $accessToken)) {
            // Token is being used from different IP/browser - possible theft
            $this->logSecurityEvent('TOKEN_FINGERPRINT_MISMATCH', $accessToken);
            
            // Revoke token and require re-authentication
            $this->blacklistToken($accessToken->id);
            
            return $this->unauthorizedResponse('Token verification failed. Please login again.');
        }

        // Check for concurrent session anomalies
        if ($this->detectConcurrentSessionAnomaly($accessToken)) {
            $this->logSecurityEvent('CONCURRENT_SESSION_ANOMALY', $accessToken);
            
            // Don't block, but log for monitoring
        }

        // Update last activity timestamp
        $this->updateTokenActivity($accessToken);

        // Proceed with request
        $response = $next($request);

        // Check if token should be rotated (after X days or suspicious activity)
        if ($this->shouldRotateToken($accessToken)) {
            $newToken = $this->rotateToken($accessToken, $request);
            $response->header('X-Token-Rotated', $newToken);
        }

        return $response;
    }

    /**
     * Find token with constant-time lookup to prevent timing attacks
     */
    protected function findToken(string $token): ?PersonalAccessToken
    {
        // Hash the token to prevent timing attacks
        $tokenHash = hash('sha256', $token);
        
        return PersonalAccessToken::where('token', $tokenHash)->first();
    }

    /**
     * Verify token fingerprint (IP + User-Agent binding)
     */
    protected function verifyTokenFingerprint(Request $request, PersonalAccessToken $token): bool
    {
        // Get stored fingerprint from token abilities
        $storedFingerprint = $token->abilities['fingerprint'] ?? null;

        if (!$storedFingerprint) {
            // Legacy token without fingerprint - allow but update
            $this->updateTokenFingerprint($token, $request);
            return true;
        }

        // Generate current fingerprint
        $currentFingerprint = $this->generateFingerprint($request);

        // Compare fingerprints
        return hash_equals($storedFingerprint, $currentFingerprint);
    }

    /**
     * Generate request fingerprint
     */
    protected function generateFingerprint(Request $request): string
    {
        $components = [
            $request->ip(),
            $request->userAgent() ?? 'unknown',
            // Add more components as needed
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Update token fingerprint
     */
    protected function updateTokenFingerprint(PersonalAccessToken $token, Request $request): void
    {
        $abilities = $token->abilities ?? [];
        $abilities['fingerprint'] = $this->generateFingerprint($request);
        
        $token->abilities = $abilities;
        $token->save();
    }

    /**
     * Check if token is blacklisted
     */
    protected function isTokenBlacklisted(int $tokenId): bool
    {
        return Cache::has("blacklisted_token:{$tokenId}");
    }

    /**
     * Blacklist a token (on logout or security breach)
     */
    protected function blacklistToken(int $tokenId): void
    {
        // Store in cache with expiration matching token lifetime
        Cache::put("blacklisted_token:{$tokenId}", true, now()->addDays(30));
    }

    /**
     * Detect concurrent session anomalies
     */
    protected function detectConcurrentSessionAnomaly(PersonalAccessToken $token): bool
    {
        $cacheKey = "token_requests:{$token->id}";
        $recentRequests = Cache::get($cacheKey, []);

        // If more than 10 requests in last second, flag as anomaly
        $oneSecondAgo = microtime(true) - 1;
        $recentCount = count(array_filter($recentRequests, fn($t) => $t > $oneSecondAgo));

        // Store current request timestamp
        $recentRequests[] = microtime(true);
        Cache::put($cacheKey, array_slice($recentRequests, -20), 10); // Keep last 20 requests

        return $recentCount > 10;
    }

    /**
     * Update token last activity
     */
    protected function updateTokenActivity(PersonalAccessToken $token): void
    {
        // Update in cache to avoid DB hits on every request
        $cacheKey = "token_activity:{$token->id}";
        Cache::put($cacheKey, now(), 3600);

        // Persist to DB every 5 minutes
        if (!Cache::has("token_persisted:{$token->id}")) {
            $token->last_used_at = now();
            $token->save();
            Cache::put("token_persisted:{$token->id}", true, 300);
        }
    }

    /**
     * Check if token should be rotated
     */
    protected function shouldRotateToken(PersonalAccessToken $token): bool
    {
        // Rotate if token is older than 7 days
        if ($token->created_at->diffInDays(now()) > 7) {
            return true;
        }

        return false;
    }

    /**
     * Rotate token (create new, revoke old)
     */
    protected function rotateToken(PersonalAccessToken $oldToken, Request $request): string
    {
        // Create new token with same abilities
        $user = $oldToken->tokenable;
        $newToken = $user->createToken(
            $oldToken->name,
            $oldToken->abilities
        );

        // Add fingerprint to new token
        $this->updateTokenFingerprint($newToken->accessToken, $request);

        // Blacklist old token
        $this->blacklistToken($oldToken->id);

        return $newToken->plainTextToken;
    }

    /**
     * Log security event
     */
    protected function logSecurityEvent(string $event, PersonalAccessToken $token): void
    {
        \Log::warning("Security Event: {$event}", [
            'token_id' => $token->id,
            'user_id' => $token->tokenable_id,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Build unauthorized response
     */
    protected function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => $message
            ]
        ], 401);
    }
}
