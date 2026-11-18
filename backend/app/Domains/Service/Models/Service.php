<?php

namespace App\Domains\Service\Models;

use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'services';

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'pricing_model',
        'base_price',
        'minimum_price',
        'maximum_price',
        'estimated_duration_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'minimum_price' => 'decimal:2',
            'maximum_price' => 'decimal:2',
            'estimated_duration_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id', 'id');
    }

    public function providerServices(): HasMany
    {
        return $this->hasMany(ProviderService::class, 'service_id', 'id');
    }
}
