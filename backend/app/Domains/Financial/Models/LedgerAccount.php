<?php

namespace App\Domains\Financial\Models;

use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class LedgerAccount extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'ledger_accounts';

    protected $fillable = [
        'account_code',
        'account_name',
        'account_type',
        'currency',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'ledger_account_id', 'id');
    }

    /**
     * Compute current real-time balance from posted transactions
     */
    public function getBalance(): float
    {
        $result = DB::table('ledger_entries')
            ->join('ledger_transactions', 'ledger_entries.ledger_transaction_id', '=', 'ledger_transactions.id')
            ->where('ledger_entries.ledger_account_id', $this->id)
            ->where('ledger_transactions.status', 'posted')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN ledger_entries.direction = 'debit' THEN ledger_entries.amount ELSE 0 END), 0) AS total_debit,
                COALESCE(SUM(CASE WHEN ledger_entries.direction = 'credit' THEN ledger_entries.amount ELSE 0 END), 0) AS total_credit
            ")->first();

        $debit = (float) $result->total_debit;
        $credit = (float) $result->total_credit;

        // Assets and Expenses are Debit normal: Debit - Credit
        if (in_array($this->account_type, ['asset', 'expense'])) {
            return $debit - $credit;
        }

        // Liabilities, Equity, and Revenue are Credit normal: Credit - Debit
        return $credit - $debit;
    }
}
