<?php

namespace App\Domains\System\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemConfiguration extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'system_configurations';

    protected $fillable = [
        'key',
        'value',
        'data_type',
        'description',
        'is_public',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }
}
