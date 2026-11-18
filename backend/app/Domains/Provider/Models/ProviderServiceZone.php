<?php

namespace App\Domains\Provider\Models;

use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderServiceZone extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'provider_service_zones';

    protected $fillable = [
        'provider_profile_id',
        'service_area_id',
        'custom_radius_km',
    ];

    protected function casts(): array
    {
        return [
            'custom_radius_km' => 'decimal:2',
        ];
    }

    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_profile_id', 'id');
    }

    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class, 'service_area_id', 'id');
    }
}
