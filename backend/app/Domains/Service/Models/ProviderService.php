<?php

namespace App\Domains\Service\Models;

use App\Domains\Provider\Models\ProviderProfile;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProviderService extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'provider_services';

    protected $fillable = [
        'provider_profile_id',
        'service_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_profile_id', 'id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProviderServicePrice::class, 'provider_service_id', 'id');
    }

    public function currentPrice(): HasOne
    {
        return $this->hasOne(ProviderServicePrice::class, 'provider_service_id', 'id')
            ->where('is_current', true)
            ->where('approval_status', 'approved');
    }
}
