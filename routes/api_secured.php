<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{EnrollmentController, SearchController, AuthController};

/*
|--------------------------------------------------------------------------
| API Routes - SECURED
|--------------------------------------------------------------------------
| 
| SECURITY FEATURES:
| ✅ All routes protected by CORS configuration
| ✅ Rate limiting applied via middleware (see Kernel.php)
| ✅ Input sanitization via SanitizeInput middleware
| ✅ Security headers via SecurityHeaders middleware
| ✅ Request logging via LogApiRequests middleware
| ✅ Authentication via Laravel Sanctum (token-based)
| ✅ HTTPS enforced in production via ForceHttps middleware
|
*/

// Public routes (no authentication required)
Route::prefix('v1')->group(function () {
    // Authentication endpoints - Stricter rate limits
    Route::middleware(['rate.limit:5,1'])->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
    });
    
    // Public search endpoint - Standard rate limit
    Route::get('search/courses', [SearchController::class, 'searchCourses']);
});

// Protected routes (authentication required)
Route::prefix('v1')->middleware(['auth:sanctum', 'validate.token'])->group(function () {
    // Authentication
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    });
    
    // Enrollment endpoints - Protected, require authentication
    Route::apiResource('enrollments', EnrollmentController::class);
    
    // Additional secured endpoints can be added here
});
