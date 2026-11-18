<?php

namespace App\Domains\Provider\Models;

use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderDayOffSchedule extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'provider_day_off_schedules';

    protected $fillable = [
        'provider_profile_id',
        'off_date',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'off_date' => 'date',
        ];
    }

    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_profile_id', 'id');
    }
}
