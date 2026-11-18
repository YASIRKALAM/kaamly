<?php

namespace App\Domains\Provider\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Booking\Models\Booking;
use App\Domains\Service\Models\ProviderService;
use App\Domains\Financial\Models\Settlement;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ProviderProfile extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'provider_profiles';

    protected $fillable = [
        'user_id',
        'business_name',
        'cnic_number',
        'cnic_encrypted',
        'status',
        'onboarding_step',
        'rating_average',
        'total_reviews',
        'completed_jobs_count',
        'acceptance_rate',
        'response_time_seconds',
        'location_updated_at',
        'is_online',
        'is_available',
        'rejection_reason',
        'approved_at',
        'approved_by',
        'suspended_at',
        'suspended_by',
    ];

    protected function casts(): array
    {
        return [
            'rating_average' => 'decimal:2',
            'acceptance_rate' => 'decimal:2',
            'is_online' => 'boolean',
            'is_available' => 'boolean',
            'location_updated_at' => 'datetime',
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
            'onboarding_step' => 'integer',
            'total_reviews' => 'integer',
            'completed_jobs_count' => 'integer',
            'response_time_seconds' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProviderDocument::class, 'provider_profile_id', 'id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(ProviderBankAccount::class, 'provider_profile_id', 'id');
    }

    public function primaryBankAccount()
    {
        return $this->hasOne(ProviderBankAccount::class, 'provider_profile_id', 'id')->where('is_primary', true);
    }

    public function services(): HasMany
    {
        return $this->hasMany(ProviderService::class, 'provider_profile_id', 'id');
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(ProviderWorkingHour::class, 'provider_profile_id', 'id');
    }

    public function dayOffSchedules(): HasMany
    {
        return $this->hasMany(ProviderDayOffSchedule::class, 'provider_profile_id', 'id');
    }

    public function availabilityExceptions(): HasMany
    {
        return $this->hasMany(ProviderAvailabilityException::class, 'provider_profile_id', 'id');
    }

    public function serviceZones(): HasMany
    {
        return $this->hasMany(ProviderServiceZone::class, 'provider_profile_id', 'id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'provider_profile_id', 'id');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class, 'provider_profile_id', 'id');
    }

    /**
     * Scope / Helper to update PostGIS current_location
     */
    public function updateLocation(float $latitude, float $longitude): void
    {
        $this->attributes['current_location'] = DB::raw("ST_SetSRID(ST_MakePoint({$longitude}, {$latitude}), 4326)");
        $this->attributes['location_updated_at'] = now();
        $this->save();
    }
}
