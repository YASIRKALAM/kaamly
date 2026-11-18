<?php

namespace App\Domains\Service\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceApprovalHistory extends Model
{
    use HasFactory, HasUuid;

    public $timestamps = false;

    protected $table = 'price_approval_history';

    protected $fillable = [
        'provider_service_price_id',
        'requested_price',
        'previous_price',
        'action',
        'reviewed_by',
        'comments',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_price' => 'decimal:2',
            'previous_price' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function providerServicePrice(): BelongsTo
    {
        return $this->belongsTo(ProviderServicePrice::class, 'provider_service_price_id', 'id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'id');
    }
}
