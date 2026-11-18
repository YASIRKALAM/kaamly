<?php

namespace App\Domains\Provider\Models;

use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderAvailabilityException extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'provider_availability_exceptions';

    protected $fillable = [
        'provider_profile_id',
        'exception_date',
        'is_available',
        'custom_start_time',
        'custom_end_time',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'exception_date' => 'date',
            'is_available' => 'boolean',
        ];
    }

    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_profile_id', 'id');
    }
}
