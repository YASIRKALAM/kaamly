<?php

namespace App\Domains\Customer\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingRequest;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerProfile extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'customer_profiles';

    protected $fillable = [
        'user_id',
        'default_address_id',
        'profile_photo_url',
        'preferred_language',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function defaultAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'default_address_id', 'id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'customer_profile_id', 'id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_profile_id', 'id');
    }

    public function bookingRequests(): HasMany
    {
        return $this->hasMany(BookingRequest::class, 'customer_profile_id', 'id');
    }
}
