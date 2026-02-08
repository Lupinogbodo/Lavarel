<?php

namespace App\Jobs;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\{Mail, Log, Cache};

/**
 * Queue job to send welcome email to newly enrolled student
 * 
 * This job is queued for asynchronous processing to avoid blocking
 * the enrollment API response. Includes retry logic and failure handling.
 */
class SendWelcomeEmail implements ShouldQueue
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
    public int $timeout = 120;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * FIX for Bug #3 (Queue Timing):
     * Ensure job only runs AFTER database transaction commits.
     * This prevents the job from trying to access data that hasn't
     * been committed yet, which could cause incomplete emails.
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
        // Load relationships if not already loaded
        if (!$this->enrollment->relationLoaded('student')) {
            $this->enrollment->load(['student', 'course']);
        }

        $student = $this->enrollment->student;
        $course = $this->enrollment->course;

        Log::info('Sending welcome email', [
            'enrollment_id' => $this->enrollment->id,
            'student_email' => $student->email,
        ]);

        try {
            // In production, use a proper Mail class
            // Mail::to($student->email)->send(new WelcomeToCourseMail($this->enrollment));
            
            // For demonstration, simulate email sending
            $emailData = [
                'to' => $student->email,
                'subject' => "Welcome to {$course->title}!",
                'body' => $this->buildEmailBody($student, $course),
                'enrollment_number' => $this->enrollment->enrollment_number,
            ];

            // Simulate email service call
            $this->sendEmail($emailData);

            // Mark email as sent in cache/database
            Cache::put(
                "enrollment_{$this->enrollment->id}_welcome_email_sent",
                true,
                now()->addDays(30)
            );

            Log::info('Welcome email sent successfully', [
                'enrollment_id' => $this->enrollment->id,
                'student_email' => $student->email,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send welcome email', [
                'enrollment_id' => $this->enrollment->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Build email body content
     */
    private function buildEmailBody($student, $course): string
    {
        return <<<EMAIL
        Dear {$student->first_name},

        Welcome to {$course->title}!

        We're excited to have you on board. Your enrollment has been confirmed and you can now access all course materials.

        Enrollment Details:
        - Enrollment Number: {$this->enrollment->enrollment_number}
        - Course: {$course->title}
        - Level: {$course->level}
        - Duration: {$course->duration_hours} hours

        To get started, log in to your account and navigate to "My Courses".

        If you have any questions, please don't hesitate to reach out to our support team.

        Best regards,
        The Learning Platform Team
        EMAIL;
    }

    /**
     * Simulate email sending (in production, use Mail facade)
     */
    private function sendEmail(array $emailData): void
    {
        // In production:
        // Mail::to($emailData['to'])
        //     ->send(new WelcomeMail($emailData));
        
        // Simulated delay
        usleep(100000); // 100ms
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Welcome email job failed after all retries', [
            'enrollment_id' => $this->enrollment->id,
            'student_email' => $this->enrollment->student->email,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // In production, notify administrators or create a support ticket
        // Notification::route('slack', config('logging.slack.webhook'))
        //     ->notify(new JobFailedNotification($this, $exception));
    }

    /**
     * Determine if the job should be retried.
     */
    public function shouldRetry(\Throwable $exception): bool
    {
        // Don't retry for certain exceptions
        if ($exception instanceof \InvalidArgumentException) {
            return false;
        }

        return true;
    }
}
