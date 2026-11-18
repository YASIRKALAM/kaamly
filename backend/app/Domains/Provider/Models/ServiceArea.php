<?php

namespace App\Domains\Provider\Models;

use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceArea extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'service_areas';

    protected $fillable = [
        'name',
        'city',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function providerZones(): HasMany
    {
        return $this->hasMany(ProviderServiceZone::class, 'service_area_id', 'id');
    }
}
