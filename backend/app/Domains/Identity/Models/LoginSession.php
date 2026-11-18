<?php

namespace App\Domains\Identity\Models;

use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginSession extends Model
{
    use HasFactory, HasUuid;

    public $timestamps = false;

    protected $table = 'login_sessions';

    protected $fillable = [
        'user_id',
        'refresh_token_hash',
        'device_id',
        'device_type',
        'ip_address',
        'user_agent',
        'expires_at',
        'revoked_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
