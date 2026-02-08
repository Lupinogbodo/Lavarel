# Caching Strategy - Learning Platform

## Overview

Redis-based caching strategy to optimize performance, reduce database load, and improve user experience.

## Cache Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Application Layer                     │
└────────────┬───────────────────────┬────────────────────┘
             │                       │
             │ Cache Miss            │ Cache Hit
             ▼                       ▼
┌────────────────────┐    ┌─────────────────────┐
│   MySQL Database   │    │   Redis Cache       │
│   (Source of Truth)│◄───┤   (Fast Access)     │
└────────────────────┘    └─────────────────────┘
             │                       │
             └───────────────────────┘
              Write Through / Aside
```

## Caching Patterns

### 1. Cache-Aside (Lazy Loading)

**Use Case**: Query results, API responses

**Flow**:
1. Check cache for data
2. If cache miss, query database
3. Store result in cache
4. Return data

**Example**:
```php
public function index(Request $request)
{
    $cacheKey = 'enrollments_' . $request->user()->id;
    
    $enrollments = Cache::remember($cacheKey, 300, function () use ($request) {
        return Enrollment::with(['student', 'course'])
            ->latest()
            ->paginate(15);
    });
    
    return response()->json($enrollments);
}
```

### 2. Write-Through Cache

**Use Case**: Critical data that must be consistent

**Flow**:
1. Write to database
2. Update cache immediately
3. Return success

**Example**:
```php
public function store()
{
    DB::transaction(function () {
        $enrollment = Enrollment::create($data);
        
        // Write to cache
        Cache::put(
            "enrollment_{$enrollment->id}",
            $enrollment,
            3600
        );
    });
}
```

### 3. Write-Behind (Queue)

**Use Case**: Analytics, non-critical updates

**Flow**:
1. Update cache immediately
2. Queue database write
3. Return success

## Cache Keys Structure

### Naming Convention

```
{resource}_{identifier}_{filters}_{page}

Examples:
- courses_search_web_all_any_1_10
- enrollment_123
- student_456_enrollments
- course_789_modules
```

### Key Patterns

| Pattern | Example | TTL | Description |
|---------|---------|-----|-------------|
| `course_{id}` | `course_123` | 15 min | Single course |
| `courses_search_{hash}` | `courses_search_a3f` | 5 min | Search results |
| `enrollments_{user_id}` | `enrollments_456` | 5 min | User enrollments |
| `enrollment_{id}` | `enrollment_789` | 10 min | Single enrollment |
| `student_{id}_progress` | `student_123_progress` | 2 min | Progress data |
| `learning_path_{id}` | `learning_path_123` | 30 min | Learning path |
| `course_materials_{id}` | `course_materials_123` | 24 hrs | Course materials |

## Cache TTL (Time To Live)

### By Data Type

| Data Type | TTL | Reasoning |
|-----------|-----|-----------|
| Search Results | 5 minutes | Frequently changing, balance freshness vs performance |
| Course Catalog | 15 minutes | Semi-static, changes infrequently |
| Enrollment Details | 10 minutes | Updated occasionally |
| Student Progress | 2 minutes | Frequently updated |
| Course Materials | 24 hours | Rarely changes |
| Configuration | 1 hour | Rarely changes |
| Session Data | 2 hours | Per Laravel session config |

### Dynamic TTL

```php
// Shorter TTL during business hours
$ttl = now()->isWeekday() && now()->hour >= 9 && now()->hour <= 17
    ? 300  // 5 minutes during peak hours
    : 900; // 15 minutes off-peak

Cache::remember($key, $ttl, $callback);
```

## Cache Invalidation

### Strategies

#### 1. Time-Based Expiration

```php
// Simple TTL
Cache::put('key', $value, 300); // 5 minutes
```

#### 2. Event-Based Invalidation

```php
// In EnrollmentController
private function invalidateEnrollmentCaches(Enrollment $enrollment): void
{
    // Invalidate specific keys
    Cache::forget("enrollment_{$enrollment->id}");
    Cache::forget("student_{$enrollment->student_id}_enrollments");
    Cache::forget("course_{$enrollment->course_id}_enrollments");
    
    // Invalidate tags (if using cache tags)
    Cache::tags(['enrollments'])->flush();
}
```

#### 3. Pattern-Based Invalidation

```php
// Clear all enrollment caches
public function clearEnrollmentCaches(): void
{
    $keys = Redis::keys('enrollments_*');
    foreach ($keys as $key) {
        Cache::forget($key);
    }
}
```

### Invalidation Triggers

| Event | Keys to Invalidate |
|-------|-------------------|
| New Enrollment | `student_{id}_enrollments`, `course_{id}_enrollments`, `enrollments_*` |
| Course Update | `course_{id}`, `courses_search_*` |
| Student Update | `student_{id}`, `enrollment_{id}` |
| Progress Update | `student_{id}_progress`, `enrollment_{id}` |
| Module/Lesson Change | `course_{id}`, `course_materials_{id}` |

## Cache Tags

**Note**: Redis doesn't natively support tags, but Laravel provides emulation

```php
// Store with tags
Cache::tags(['courses', 'enrollments'])->put('key', $value, 300);

// Invalidate by tag
Cache::tags('courses')->flush();
```

### Tag Structure

- `students` - All student-related data
- `courses` - All course-related data
- `enrollments` - All enrollment data
- `search` - All search results
- `analytics` - Analytics data

## Performance Optimization

### 1. Cache Warming

Pre-populate cache with frequently accessed data:

```php
// In a scheduled job (e.g., every hour)
Artisan::command('cache:warm', function () {
    // Warm popular courses
    $popularCourses = Course::where('enrolled_count', '>', 100)
        ->get();
    
    foreach ($popularCourses as $course) {
        Cache::put("course_{$course->id}", $course, 3600);
    }
    
    $this->info('Cache warmed successfully');
})->daily();
```

### 2. Cache Compression

For large datasets:

```php
$data = LargeDataset::all();
$compressed = gzcompress(json_encode($data));
Cache::put('large_dataset', $compressed, 3600);

// Retrieval
$compressed = Cache::get('large_dataset');
$data = json_decode(gzuncompress($compressed));
```

### 3. Partial Caching

Cache expensive query parts:

```php
// Cache the count separately
$totalEnrollments = Cache::remember('enrollments_count', 600, function () {
    return Enrollment::count();
});

// Query only needed data
$enrollments = Enrollment::latest()->limit(10)->get();
```

## Redis Configuration

### config/database.php

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'predis'),
    
    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => 0,  // Cache
        'read_timeout' => 60,
        'context' => [
            'tcp_keepalive' => 1,
        ],
    ],
    
    'cache' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => 1,  // Separate DB for cache
    ],
    
    'session' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => 2,  // Separate DB for sessions
    ],
    
    'queue' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => 3,  // Separate DB for queues
    ],
],
```

### config/cache.php

```php
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
],

'prefix' => env('CACHE_PREFIX', 'learning_platform_cache'),
```

## Monitoring & Metrics

### Key Metrics

1. **Hit Rate**
   ```
   Hit Rate = (Cache Hits / (Cache Hits + Cache Misses)) × 100
   Target: > 80%
   ```

2. **Memory Usage**
   ```
   Monitor Redis memory consumption
   Set maxmemory policy: allkeys-lru
   ```

3. **Eviction Rate**
   ```
   Track evicted keys
   Adjust TTL or memory if high eviction
   ```

### Redis Commands for Monitoring

```bash
# Check memory usage
redis-cli INFO memory

# Monitor cache operations in real-time
redis-cli MONITOR

# Check hit/miss stats
redis-cli INFO stats

# List all keys (use cautiously in production)
redis-cli KEYS *

# Get TTL for a key
redis-cli TTL key_name
```

## Best Practices

### 1. Always Set TTL

```php
// ❌ BAD: No TTL (memory leak risk)
Cache::forever('key', $value);

// ✅ GOOD: Always set reasonable TTL
Cache::put('key', $value, 300);
```

### 2. Handle Cache Failures Gracefully

```php
try {
    $data = Cache::remember($key, $ttl, function () {
        return ExpensiveQuery::get();
    });
} catch (\Exception $e) {
    // Log error but don't fail the request
    Log::warning('Cache error', ['error' => $e->getMessage()]);
    
    // Fall back to database
    $data = ExpensiveQuery::get();
}
```

### 3. Avoid Caching Sensitive Data

```php
// ❌ BAD: Don't cache sensitive data
Cache::put('user_password', $password, 3600);

// ✅ GOOD: Only cache non-sensitive data
Cache::put('user_preferences', $preferences, 3600);
```

### 4. Use Cache Locks for Race Conditions

```php
$lock = Cache::lock('enrollment_' . $courseId, 10);

if ($lock->get()) {
    try {
        // Process enrollment
        $enrollment = $this->createEnrollment($data);
    } finally {
        $lock->release();
    }
}
```

## Production Considerations

### 1. Redis Persistence

Configure Redis for data persistence:

```conf
# redis.conf
save 900 1      # Save if 1 key changed in 15 minutes
save 300 10     # Save if 10 keys changed in 5 minutes
save 60 10000   # Save if 10000 keys changed in 1 minute
```

### 2. Memory Management

```conf
# redis.conf
maxmemory 2gb
maxmemory-policy allkeys-lru  # Evict least recently used keys
```

### 3. Replication for High Availability

```
Primary Redis  →  Replica Redis 1
               →  Replica Redis 2
```

### 4. Cluster for Scale

For very large applications:
- Redis Cluster with multiple shards
- Consistent hashing for key distribution
- Automatic failover

## Cache Performance Impact

### Without Cache

| Operation | Response Time |
|-----------|--------------|
| Course Search | ~500ms |
| Enrollment List | ~300ms |
| Course Details | ~200ms |

### With Cache (80%+ hit rate)

| Operation | Response Time |
|-----------|--------------|
| Course Search | ~50ms |
| Enrollment List | ~30ms |
| Course Details | ~20ms |

**Performance Improvement: 85-90% reduction in response time**
