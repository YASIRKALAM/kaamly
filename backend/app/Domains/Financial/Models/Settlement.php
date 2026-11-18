<?php

namespace App\Domains\Financial\Models;

use App\Domains\Provider\Models\ProviderProfile;
use App\Domains\Provider\Models\ProviderBankAccount;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Settlement extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'settlements';

    protected $fillable = [
        'settlement_number',
        'provider_profile_id',
        'provider_bank_account_id',
        'amount',
        'currency',
        'status',
        'payout_reference',
        'processed_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_profile_id', 'id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(ProviderBankAccount::class, 'provider_bank_account_id', 'id');
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class, 'settlement_id', 'id');
    }
}
