<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enrollment_number' => $this->enrollment_number,
            'status' => $this->status,
            
            // Student information
            'student' => [
                'id' => $this->student->id,
                'name' => $this->student->full_name,
                'email' => $this->student->email,
                'phone' => $this->student->phone,
            ],
            
            // Course information
            'course' => [
                'id' => $this->course->id,
                'code' => $this->course->code,
                'title' => $this->course->title,
                'level' => $this->course->level,
                'duration_hours' => $this->course->duration_hours,
            ],
            
            // Payment information
            'payment' => [
                'transaction_id' => $this->payment->transaction_id ?? null,
                'amount' => (float) $this->amount_paid,
                'discount' => (float) $this->discount_applied,
                'coupon_code' => $this->coupon_code,
                'status' => $this->payment->status ?? null,
                'paid_at' => $this->payment?->paid_at?->toIso8601String(),
            ],
            
            // Progress information
            'progress' => [
                'percentage' => $this->progress_percentage,
                'enrolled_at' => $this->enrolled_at?->toIso8601String(),
                'started_at' => $this->started_at?->toIso8601String(),
                'completed_at' => $this->completed_at?->toIso8601String(),
                'expires_at' => $this->expires_at?->toIso8601String(),
            ],
            
            // Additional data
            'custom_fields' => $this->custom_fields,
            'notes' => $this->notes,
            
            // Timestamps
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
