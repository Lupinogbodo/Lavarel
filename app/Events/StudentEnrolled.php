<?php

namespace App\Events;

use App\Models\Enrollment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a student is successfully enrolled in a course
 * 
 * This event can trigger multiple listeners:
 * - Send notifications to instructors
 * - Update analytics
 * - Trigger integrations with third-party systems
 * - Update recommendation engines
 */
class StudentEnrolled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Enrollment $enrollment;

    /**
     * Create a new event instance.
     */
    public function __construct(Enrollment $enrollment)
    {
        $this->enrollment = $enrollment;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('enrollments'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'enrollment_id' => $this->enrollment->id,
            'enrollment_number' => $this->enrollment->enrollment_number,
            'student_name' => $this->enrollment->student->full_name,
            'course_title' => $this->enrollment->course->title,
            'enrolled_at' => $this->enrollment->enrolled_at,
        ];
    }
}
