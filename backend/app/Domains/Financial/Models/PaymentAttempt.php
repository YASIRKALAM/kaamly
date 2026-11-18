<?php

namespace App\Domains\Financial\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentAttempt extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'payment_attempts';

    protected $fillable = [
        'booking_id',
        'gateway_name',
        'gateway_reference',
        'amount',
        'currency',
        'status',
        'failure_reason',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payload' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'payment_attempt_id', 'id');
    }
}
