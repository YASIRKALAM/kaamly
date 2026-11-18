<?php

namespace App\Domains\Provider\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderStatusHistory extends Model
{
    use HasFactory, HasUuid;

    public $timestamps = false;

    protected $table = 'provider_status_history';

    protected $fillable = [
        'provider_profile_id',
        'previous_status',
        'new_status',
        'reason',
        'changed_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_profile_id', 'id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by', 'id');
    }
}
