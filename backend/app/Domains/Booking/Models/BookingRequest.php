<?php

namespace App\Domains\Booking\Models;

use App\Domains\Customer\Models\CustomerProfile;
use App\Domains\Customer\Models\CustomerAddress;
use App\Domains\Service\Models\Service;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BookingRequest extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'booking_requests';

    protected $fillable = [
        'customer_profile_id',
        'service_id',
        'address_id',
        'requested_schedule_start',
        'requested_schedule_end',
        'notes',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_schedule_start' => 'datetime',
            'requested_schedule_end' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_profile_id', 'id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'address_id', 'id');
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class, 'booking_request_id', 'id');
    }
}
