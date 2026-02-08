<?php

namespace App\Listeners;

use App\Events\StudentEnrolled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\{Log, Notification};

/**
 * Listener to notify instructors when a new student enrolls
 * 
 * This listener is queued for asynchronous processing
 */
class NotifyInstructors implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(StudentEnrolled $event): void
    {
        $enrollment = $event->enrollment;
        $enrollment->load(['student', 'course']);

        Log::info('Notifying instructors of new enrollment', [
            'enrollment_id' => $enrollment->id,
            'course_id' => $enrollment->course_id,
        ]);

        // In production, fetch instructors from database
        // $instructors = $enrollment->course->instructors;
        // 
        // foreach ($instructors as $instructor) {
        //     $instructor->notify(new NewStudentEnrolled($enrollment));
        // }

        // For demonstration, log the notification
        $this->sendNotification($enrollment);
    }

    /**
     * Send notification to instructors
     */
    private function sendNotification($enrollment): void
    {
        $message = sprintf(
            "New student enrolled: %s has enrolled in %s",
            $enrollment->student->full_name,
            $enrollment->course->title
        );

        Log::info('Instructor notification', [
            'message' => $message,
            'enrollment_number' => $enrollment->enrollment_number,
        ]);

        // In production, send actual notifications:
        // - Email
        // - Slack
        // - SMS
        // - In-app notification
    }

    /**
     * Handle a failed job.
     */
    public function failed(StudentEnrolled $event, \Throwable $exception): void
    {
        Log::error('Failed to notify instructors', [
            'enrollment_id' => $event->enrollment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
