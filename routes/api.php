<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\SearchController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->middleware(['api', 'throttle:60,1'])->group(function () {
    
    // Public routes - No authentication required
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // Search endpoint (public for demo)
    Route::get('/search/courses', [SearchController::class, 'searchCourses']);
    
    // Protected routes - Require authentication
    Route::middleware('auth:sanctum')->group(function () {
        
        // Logout
        Route::post('/logout', [AuthController::class, 'logout']);
        
        // Main enrollment endpoint
        Route::post('/enrollments', [EnrollmentController::class, 'store'])
            ->middleware('throttle:10,1');
        
        Route::get('/enrollments', [EnrollmentController::class, 'index']);
        Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show']);
    });
});
