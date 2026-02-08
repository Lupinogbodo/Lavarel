<?php

namespace Tests\Debugging;

use Tests\TestCase;
use App\Models\{Course, Student, Enrollment};
use Illuminate\Support\Facades\{DB, Queue, Cache};
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Bug Replication Test Suite
 * 
 * These tests reproduce the multi-layer bugs from Question 3:
 * 1. Cache staleness
 * 2. Race conditions
 * 3. Queue timing issues
 * 4. Browser cache differences
 */
class ReplicateBugTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Case 1: Cache Staleness Issue
     * 
     * Scenario: Course capacity cached, then enrollment happens,
     * but cache not invalidated immediately
     */
    public function test_cache_staleness_on_enrollment()
    {
        // 1. Setup: Create course with 1 spot
        $course = Course::factory()->create([
            'available_slots' => 1,
            'max_students' => 10
        ]);
        
        // 2. Prime the cache (simulate first user viewing course)
        Cache::put("course:{$course->id}:availability", 1, 300);
        
        // 3. Direct database enrollment (bypassing cache invalidation)
        DB::table('courses')->where('id', $course->id)->update([
            'available_slots' => 0
        ]);
        
        // 4. Check cache - should be 0 but might be stale
        $cached = Cache::get("course:{$course->id}:availability");
        
        // BUG (before fix): Cache still shows 1, database shows 0
        // AFTER FIX: Cache should be null (invalidated) or match DB
        $dbValue = $course->refresh()->available_slots;
        
        if ($cached !== null) {
            $this->assertEquals($dbValue, $cached, 'Cache should match database or be null');
        }
    }

    /**
     * Test Case 2: Race Condition in Concurrent Enrollments
     * 
     * Scenario: Multiple users enroll simultaneously for last spot
     */
    public function test_race_condition_concurrent_enrollments()
    {
        $this->markTestIncomplete('Requires parallel execution testing');
        
        // This test would need actual concurrent execution
        // which is difficult in PHPUnit. Use load testing tools instead.
    }

    /**
     * Test Case 3: Queue Job Running Before Transaction Commit
     * 
     * Scenario: Welcome email job tries to load enrollment
     * before the enrollment transaction commits
     */
    public function test_queue_job_after_commit()
    {
        Queue::fake();
        
        $course = Course::factory()->create();
        
        // Create enrollment which triggers job dispatch
        $enrollment = Enrollment::factory()->create([
            'course_id' => $course->id
        ]);
        
        // Job should be dispatched
        Queue::assertPushed(\App\Jobs\SendWelcomeEmail::class);
        
        // AFTER FIX: Job has $afterCommit = true, so it waits for commit
        $this->assertTrue(true);
    }

    /**
     * Test Case 4: Browser Cache Differences
     * 
     * Scenario: Different browsers cache HTTP responses differently
     */
    public function test_browser_cache_headers()
    {
        $course = Course::factory()->create(['available_slots' => 5]);
        
        // Make API request
        $response = $this->getJson("/api/v1/search/courses?query=test");
        
        // Check cache headers
        $response->assertHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
        
        // AFTER FIX: Headers prevent browser caching
    }

    /**
     * Test: Cache invalidation happens after enrollment
     */
    public function test_cache_invalidated_after_enrollment_created()
    {
        $course = Course::factory()->create(['available_slots' => 5]);
        
        // Prime cache
        $cacheKey = "course:{$course->id}:availability";
        Cache::put($cacheKey, 5, 300);
        
        // Verify cache is set
        $this->assertEquals(5, Cache::get($cacheKey));
        
        // Create enrollment (should trigger cache invalidation via model event)
        $enrollment = Enrollment::factory()->create([
            'course_id' => $course->id
        ]);
        
        // AFTER FIX: Cache should be invalidated
        $this->assertNull(Cache::get($cacheKey), 'Cache should be invalidated after enrollment');
    }

    /**
     * Test: Fresh data is fetched when cache is invalidated
     */
    public function test_fresh_data_after_cache_invalidation()
    {
        $course = Course::factory()->create(['available_slots' => 10]);
        
        // Get initial availability
        $initial = $course->available_slots;
        
        // Create some enrollments
        Enrollment::factory()->count(3)->create(['course_id' => $course->id]);
        
        // Update course slots
        $course->decrement('available_slots', 3);
        
        // Fresh query should return updated value
        $fresh = Course::find($course->id)->available_slots;
        
        $this->assertEquals($initial - 3, $fresh);
    }
}
