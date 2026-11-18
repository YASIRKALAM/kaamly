<?php

namespace App\Domains\Booking\Models;

use App\Domains\Customer\Models\CustomerProfile;
use App\Domains\Customer\Models\CustomerAddress;
use App\Domains\Provider\Models\ProviderProfile;
use App\Domains\Service\Models\Service;
use App\Domains\Identity\Models\User;
use App\Domains\Communication\Models\Conversation;
use App\Domains\Review\Models\Review;
use App\Domains\Review\Models\Dispute;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'bookings';

    protected $fillable = [
        'booking_number',
        'customer_profile_id',
        'provider_profile_id',
        'booking_request_id',
        'address_id',
        'service_id',
        'status',
        'payment_mode',
        'payment_status',
        'scheduled_start_at',
        'scheduled_end_at',
        'actual_started_at',
        'actual_completed_at',
        'base_price',
        'additional_charges',
        'discount_amount',
        'commission_rate',
        'platform_fee',
        'agreed_total_price',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'cancellation_fee',
        'no_show_by',
        'no_show_responsible_party',
        'no_show_reason',
        'no_show_evidence',
        'no_show_recorded_by',
        'no_show_recorded_at',
        'reschedule_requested_by',
        'reschedule_reason',
        'rescheduled_start_at',
        'rescheduled_end_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'actual_started_at' => 'datetime',
            'actual_completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'no_show_recorded_at' => 'datetime',
            'rescheduled_start_at' => 'datetime',
            'rescheduled_end_at' => 'datetime',
            'no_show_evidence' => 'array',
            'base_price' => 'decimal:2',
            'additional_charges' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'agreed_total_price' => 'decimal:2',
            'cancellation_fee' => 'decimal:2',
        ];
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_profile_id', 'id');
    }

    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_profile_id', 'id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'address_id', 'id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by', 'id');
    }

    public function rescheduleRequestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reschedule_requested_by', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class, 'booking_id', 'id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'booking_id', 'id');
    }

    public function acceptedQuotation(): HasOne
    {
        return $this->hasOne(Quotation::class, 'booking_id', 'id')->where('status', 'accepted');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class, 'booking_id', 'id')->orderBy('created_at', 'asc');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(BookingAttachment::class, 'booking_id', 'id');
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class, 'booking_id', 'id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'booking_id', 'id');
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(Dispute::class, 'booking_id', 'id');
    }
}
