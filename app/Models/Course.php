<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'title',
        'description',
        'slug',
        'price',
        'discount_price',
        'level',
        'status',
        'duration_hours',
        'max_students',
        'enrolled_count',
        'start_date',
        'end_date',
        'tags',
        'prerequisites',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'tags' => 'array',
        'prerequisites' => 'array',
    ];

    /**
     * Get all modules for the course
     */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('order');
    }

    /**
     * Get all enrollments for the course
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Check if course is published
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Check if course has available slots
     */
    public function hasAvailableSlots(): bool
    {
        if ($this->max_students === null) {
            return true;
        }
        
        return $this->enrolled_count < $this->max_students;
    }

    /**
     * Get effective price (considering discount)
     */
    public function getEffectivePriceAttribute(): float
    {
        return $this->discount_price ?? $this->price;
    }

    /**
     * Increment enrolled count
     */
    public function incrementEnrolledCount(): void
    {
        $this->increment('enrolled_count');
    }
}
