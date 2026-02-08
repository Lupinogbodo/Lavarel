<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{Cache, DB};

/**
 * Search Controller for course search functionality
 */
class SearchController extends Controller
{
    /**
     * Search courses with caching and full-text search
     * 
     * Features:
     * - Full-text search on title and description
     * - Filter by level, status, price range
     * - Redis caching for performance
     * - Pagination
     */
    public function searchCourses(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $level = $request->input('level');
        $maxPrice = $request->input('max_price');
        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);

        // Generate cache key based on search parameters
        $cacheKey = $this->generateCacheKey($query, $level, $maxPrice, $page, $perPage);

        // Cache results for 5 minutes
        $results = Cache::remember($cacheKey, 300, function () use ($query, $level, $maxPrice, $perPage) {
            $coursesQuery = Course::query()
                ->where('status', 'published')
                ->select([
                    'id',
                    'code',
                    'title',
                    'description',
                    'slug',
                    'price',
                    'discount_price',
                    'level',
                    'duration_hours',
                    'enrolled_count',
                    'tags',
                ]);

            // Full-text search or LIKE fallback
            if (!empty($query)) {
                // For MySQL 8.0+ with full-text index
                // $coursesQuery->whereRaw('MATCH(title, description) AGAINST(? IN NATURAL LANGUAGE MODE)', [$query]);
                
                // Fallback: LIKE search
                $coursesQuery->where(function ($q) use ($query) {
                    $q->where('title', 'LIKE', "%{$query}%")
                      ->orWhere('description', 'LIKE', "%{$query}%")
                      ->orWhere('code', 'LIKE', "%{$query}%");
                });
            }

            // Apply filters
            if ($level) {
                $coursesQuery->where('level', $level);
            }

            if ($maxPrice) {
                $coursesQuery->where(function ($q) use ($maxPrice) {
                    $q->where('discount_price', '<=', $maxPrice)
                      ->orWhere(function ($q2) use ($maxPrice) {
                          $q2->whereNull('discount_price')
                             ->where('price', '<=', $maxPrice);
                      });
                });
            }

            return $coursesQuery
                ->orderByRaw('enrolled_count DESC, title ASC')
                ->paginate($perPage);
        });

        return response()->json([
            'success' => true,
            'query' => $query,
            'data' => $results->items(),
            'meta' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'from' => $results->firstItem(),
                'to' => $results->lastItem(),
            ],
        ]);
    }

    /**
     * Generate cache key for search results
     */
    private function generateCacheKey(string $query, ?string $level, ?float $maxPrice, int $page, int $perPage): string
    {
        return sprintf(
            'courses_search_%s_%s_%s_%d_%d',
            md5(strtolower($query)),
            $level ?? 'all',
            $maxPrice ?? 'any',
            $page,
            $perPage
        );
    }
}
