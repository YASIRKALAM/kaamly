<?php

namespace App\Domains\Customer\Models;

use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class CustomerAddress extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'customer_addresses';

    protected $fillable = [
        'customer_profile_id',
        'label',
        'address_line_1',
        'address_line_2',
        'area',
        'city',
        'postal_code',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_profile_id', 'id');
    }

    /**
     * Scope to set PostGIS point location from latitude & longitude
     */
    public function setLocation(float $latitude, float $longitude): void
    {
        $this->attributes['location'] = DB::raw("ST_SetSRID(ST_MakePoint({$longitude}, {$latitude}), 4326)");
    }
}
