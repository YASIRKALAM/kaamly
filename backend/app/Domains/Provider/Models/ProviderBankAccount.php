<?php

namespace App\Domains\Provider\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderBankAccount extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'provider_bank_accounts';

    protected $fillable = [
        'provider_profile_id',
        'bank_name',
        'account_title',
        'account_number_encrypted',
        'iban_encrypted',
        'is_verified',
        'is_primary',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_profile_id', 'id');
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by', 'id');
    }
}
