<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'title',
        'description',
        'content',
        'type',
        'video_url',
        'duration_minutes',
        'order',
        'is_preview',
        'is_published',
        'resources',
    ];

    protected $casts = [
        'is_preview' => 'boolean',
        'is_published' => 'boolean',
        'resources' => 'array',
    ];

    /**
     * Get the module that owns the lesson
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
