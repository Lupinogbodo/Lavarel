<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'phone',
        'date_of_birth',
        'address',
        'city',
        'country',
        'postal_code',
        'status',
        'preferences',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'preferences' => 'array',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Get all enrollments for the student
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Get active enrollments only
     */
    public function activeEnrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)->where('status', 'active');
    }

    /**
     * Check if student is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get full name attribute
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
