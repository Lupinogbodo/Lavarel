<?php

namespace App\Jobs;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\{Log, Cache, DB};

/**
 * Queue job to process course access and setup
 * 
 * This job handles post-enrollment setup:
 * - Generate access credentials
 * - Create learning path
 * - Set up progress tracking
 * - Initialize course materials
 * - Configure notifications
 */
class ProcessCourseAccess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Enrollment $enrollment;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 120;

    /**
     * FIX for Bug #3 (Queue Timing):
     * Ensure job only runs AFTER database transaction commits.
     * This prevents accessing enrollment data before it's persisted.
     */
    public bool $afterCommit = true;

    /**
     * Create a new job instance.
     */
    public function __construct(Enrollment $enrollment)
    {
        $this->enrollment = $enrollment;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Processing course access', [
            'enrollment_id' => $this->enrollment->id,
        ]);

        try {
            DB::transaction(function () {
                // Load relationships
                $this->enrollment->load(['student', 'course.modules.lessons']);

                // Step 1: Initialize lesson progress if not already done
                $this->initializeProgressTracking();

                // Step 2: Generate access credentials/tokens
                $accessToken = $this->generateAccessToken();

                // Step 3: Set up personalized learning path
                $this->setupLearningPath();

                // Step 4: Configure notification preferences
                $this->configureNotifications();

                // Step 5: Prepare course materials
                $this->prepareCourseMateriels();

                // Step 6: Update enrollment status if needed
                if ($this->enrollment->status === 'pending') {
                    $this->enrollment->status = 'active';
                    $this->enrollment->started_at = now();
                    $this->enrollment->save();
                }

                // Cache course access data
                Cache::put(
                    "enrollment_{$this->enrollment->id}_access",
                    [
                        'token' => $accessToken,
                        'course_structure' => $this->getCourseStructure(),
                        'processed_at' => now(),
                    ],
                    now()->addDays(7)
                );
            });

            Log::info('Course access processed successfully', [
                'enrollment_id' => $this->enrollment->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process course access', [
                'enrollment_id' => $this->enrollment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Initialize progress tracking for all lessons
     */
    private function initializeProgressTracking(): void
    {
        $course = $this->enrollment->course;
        $modulesCount = 0;
        $lessonsCount = 0;

        foreach ($course->modules as $module) {
            $modulesCount++;
            
            foreach ($module->lessons as $lesson) {
                $lessonsCount++;
                
                // Create progress record if doesn't exist
                DB::table('lesson_progress')->updateOrInsert(
                    [
                        'enrollment_id' => $this->enrollment->id,
                        'lesson_id' => $lesson->id,
                    ],
                    [
                        'is_completed' => false,
                        'time_spent_minutes' => 0,
                        'attempts' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        Log::info('Progress tracking initialized', [
            'enrollment_id' => $this->enrollment->id,
            'modules' => $modulesCount,
            'lessons' => $lessonsCount,
        ]);
    }

    /**
     * Generate secure access token for course materials
     */
    private function generateAccessToken(): string
    {
        $token = hash('sha256', $this->enrollment->id . $this->enrollment->student_id . now() . uniqid());
        
        // In production, use Laravel Sanctum or Passport
        // $token = $this->enrollment->student->createToken('course-access')->plainTextToken;
        
        return $token;
    }

    /**
     * Set up personalized learning path based on student preferences
     */
    private function setupLearningPath(): void
    {
        $preferences = $this->enrollment->student->preferences ?? [];
        $courseModules = $this->enrollment->course->modules;

        // Build personalized path
        $learningPath = [
            'enrollment_id' => $this->enrollment->id,
            'recommended_pace' => $this->calculateRecommendedPace(),
            'suggested_schedule' => $this->generateSuggestedSchedule($courseModules),
            'difficulty_adjustment' => $preferences['difficulty_preference'] ?? 'standard',
        ];

        // Store in cache for quick access
        Cache::put(
            "learning_path_{$this->enrollment->id}",
            $learningPath,
            now()->addDays(30)
        );

        Log::info('Learning path created', ['enrollment_id' => $this->enrollment->id]);
    }

    /**
     * Configure notification preferences for the student
     */
    private function configureNotifications(): void
    {
        $preferences = $this->enrollment->student->preferences['notifications'] ?? [];

        $notificationSettings = [
            'email_enabled' => $preferences['email'] ?? true,
            'sms_enabled' => $preferences['sms'] ?? false,
            'push_enabled' => $preferences['push'] ?? true,
            'reminders' => [
                'lesson_due' => true,
                'module_complete' => true,
                'course_update' => true,
            ],
        ];

        Cache::put(
            "notifications_{$this->enrollment->id}",
            $notificationSettings,
            now()->addDays(30)
        );
    }

    /**
     * Prepare and cache course materials for faster access
     */
    private function prepareCourseMateriels(): void
    {
        $materials = [
            'syllabus' => $this->generateSyllabus(),
            'resources' => $this->collectCourseResources(),
            'first_lessons' => $this->getFirstLessons(3),
        ];

        Cache::put(
            "course_materials_{$this->enrollment->id}",
            $materials,
            now()->addHours(24)
        );
    }

    /**
     * Calculate recommended learning pace
     */
    private function calculateRecommendedPace(): array
    {
        $totalHours = $this->enrollment->course->duration_hours;
        $weeksToComplete = 12; // Default 12 weeks

        return [
            'hours_per_week' => round($totalHours / $weeksToComplete, 1),
            'lessons_per_week' => round($this->enrollment->course->modules()->withCount('lessons')->get()->sum('lessons_count') / $weeksToComplete, 1),
            'estimated_completion' => now()->addWeeks($weeksToComplete)->format('Y-m-d'),
        ];
    }

    /**
     * Generate suggested schedule
     */
    private function generateSuggestedSchedule($modules): array
    {
        $schedule = [];
        $currentWeek = 1;
        
        foreach ($modules as $index => $module) {
            $schedule[] = [
                'week' => $currentWeek,
                'module_id' => $module->id,
                'module_title' => $module->title,
                'estimated_hours' => ceil($module->duration_minutes / 60),
            ];
            
            if (($index + 1) % 2 === 0) {
                $currentWeek++;
            }
        }

        return $schedule;
    }

    /**
     * Generate course syllabus
     */
    private function generateSyllabus(): array
    {
        return [
            'course_title' => $this->enrollment->course->title,
            'description' => $this->enrollment->course->description,
            'total_modules' => $this->enrollment->course->modules()->count(),
            'total_duration' => $this->enrollment->course->duration_hours,
        ];
    }

    /**
     * Collect all course resources
     */
    private function collectCourseResources(): array
    {
        return [
            'downloadable_materials' => [],
            'external_references' => [],
            'recommended_books' => [],
        ];
    }

    /**
     * Get first N lessons to get started
     */
    private function getFirstLessons(int $count): array
    {
        $lessons = [];
        $collected = 0;

        foreach ($this->enrollment->course->modules as $module) {
            foreach ($module->lessons as $lesson) {
                if ($collected >= $count) break 2;
                
                $lessons[] = [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'type' => $lesson->type,
                    'duration_minutes' => $lesson->duration_minutes,
                ];
                
                $collected++;
            }
        }

        return $lessons;
    }

    /**
     * Get course structure for caching
     */
    private function getCourseStructure(): array
    {
        return $this->enrollment->course->modules->map(function ($module) {
            return [
                'id' => $module->id,
                'title' => $module->title,
                'lessons_count' => $module->lessons->count(),
                'duration_minutes' => $module->duration_minutes,
            ];
        })->toArray();
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Course access processing failed', [
            'enrollment_id' => $this->enrollment->id,
            'error' => $exception->getMessage(),
        ]);

        // Mark enrollment as having issues
        $this->enrollment->update([
            'notes' => ($this->enrollment->notes ?? '') . "\nCourse access setup failed: " . $exception->getMessage(),
        ]);
    }
}
