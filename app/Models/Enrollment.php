<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Model Events - Boot method
     * 
     * FIX for Bug #1 (Cache Staleness):
     * Invalidate course cache AFTER enrollment transaction commits
     * to prevent race conditions with cache reads during transaction.
     */
    protected static function booted()
    {
        // After enrollment is created (transaction committed)
        static::created(function ($enrollment) {
            // Invalidate course availability cache
            \Cache::forget("course:{$enrollment->course_id}:availability");
            \Cache::forget("course:{$enrollment->course_id}:details");
            
            \Log::info('Cache invalidated after enrollment', [
                'enrollment_id' => $enrollment->id,
                'course_id' => $enrollment->course_id,
            ]);
        });
        
        // After enrollment is deleted
        static::deleted(function ($enrollment) {
            // Invalidate course cache to update availability
            \Cache::forget("course:{$enrollment->course_id}:availability");
            \Cache::forget("course:{$enrollment->course_id}:details");
        });
    }

    protected $fillable = [
        'enrollment_number',
        'student_id',
        'course_id',
        'status',
        'amount_paid',
        'discount_applied',
        'coupon_code',
        'enrolled_at',
        'started_at',
        'completed_at',
        'expires_at',
        'progress_percentage',
        'custom_fields',
        'notes',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'discount_applied' => 'decimal:2',
        'enrolled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'custom_fields' => 'array',
    ];

    /**
     * Get the student that owns the enrollment
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the course that owns the enrollment
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the payment for the enrollment
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Get lesson progress records
     */
    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * Check if enrollment is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Generate unique enrollment number
     */
    public static function generateEnrollmentNumber(): string
    {
        return 'ENR-' . date('Y') . '-' . strtoupper(substr(uniqid(), -8));
    }
}
