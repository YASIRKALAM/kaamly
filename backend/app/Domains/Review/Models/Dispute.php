<?php

namespace App\Domains\Review\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'disputes';

    protected $fillable = [
        'booking_id',
        'raised_by_user_id',
        'dispute_reason',
        'dispute_status',
        'customer_claim_amount',
        'provider_counter_claim_amount',
        'resolved_refund_amount',
        'resolved_payout_amount',
        'arbitrator_user_id',
        'arbitration_notes',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'customer_claim_amount' => 'decimal:2',
            'provider_counter_claim_amount' => 'decimal:2',
            'resolved_refund_amount' => 'decimal:2',
            'resolved_payout_amount' => 'decimal:2',
            'resolved_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function raisedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_user_id', 'id');
    }

    public function arbitrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'arbitrator_user_id', 'id');
    }
}
