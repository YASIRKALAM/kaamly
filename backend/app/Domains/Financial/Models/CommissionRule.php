<?php

namespace App\Domains\Financial\Models;

use App\Domains\Service\Models\Service;
use App\Domains\Service\Models\ServiceCategory;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRule extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'commission_rules';

    protected $fillable = [
        'service_id',
        'category_id',
        'rate_percentage',
        'flat_fee',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate_percentage' => 'decimal:2',
            'flat_fee' => 'decimal:2',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id', 'id');
    }
}
