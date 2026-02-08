<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{
    use HasFactory;

    protected $table = 'lesson_progress';

    protected $fillable = [
        'enrollment_id',
        'lesson_id',
        'is_completed',
        'time_spent_minutes',
        'attempts',
        'score',
        'started_at',
        'completed_at',
        'data',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'score' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'data' => 'array',
    ];

    /**
     * Get the enrollment that owns the progress
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * Get the lesson that owns the progress
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
