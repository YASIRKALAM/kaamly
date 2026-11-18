<?php

namespace App\Domains\Financial\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementItem extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'settlement_items';

    protected $fillable = [
        'settlement_id',
        'booking_id',
        'gross_amount',
        'commission_deducted',
        'penalty_deducted',
        'net_payout_amount',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'commission_deducted' => 'decimal:2',
            'penalty_deducted' => 'decimal:2',
            'net_payout_amount' => 'decimal:2',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class, 'settlement_id', 'id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }
}
