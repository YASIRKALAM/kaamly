<?php

namespace App\Domains\Booking\Models;

use App\Domains\Provider\Models\ProviderProfile;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'quotations';

    protected $fillable = [
        'booking_id',
        'provider_profile_id',
        'version',
        'inspection_notes',
        'labor_cost',
        'material_cost',
        'total_quote_amount',
        'status',
        'expires_at',
        'customer_rejected_reason',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'labor_cost' => 'decimal:2',
            'material_cost' => 'decimal:2',
            'total_quote_amount' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_profile_id', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class, 'quotation_id', 'id');
    }
}
