<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'enrollment_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'payment_gateway',
        'gateway_transaction_id',
        'metadata',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the enrollment that owns the payment
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * Generate unique transaction ID
     */
    public static function generateTransactionId(): string
    {
        return 'TXN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -10));
    }

    /**
     * Check if payment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
