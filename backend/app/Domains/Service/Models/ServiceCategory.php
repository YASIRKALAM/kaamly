<?php

namespace App\Domains\Service\Models;

use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'service_categories';

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'icon_url',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'parent_id', 'id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ServiceCategory::class, 'parent_id', 'id')->orderBy('display_order');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id', 'id');
    }
}
