<?php

namespace App\Domains\Service\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderServicePrice extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'provider_service_prices';

    protected $fillable = [
        'provider_service_id',
        'custom_price',
        'approval_status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'is_current',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'custom_price' => 'decimal:2',
            'is_current' => 'boolean',
            'approved_at' => 'datetime',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function providerService(): BelongsTo
    {
        return $this->belongsTo(ProviderService::class, 'provider_service_id', 'id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    public function approvalHistory(): HasMany
    {
        return $this->hasMany(PriceApprovalHistory::class, 'provider_service_price_id', 'id');
    }
}
