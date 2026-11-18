<?php

namespace App\Domains\Financial\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Financial\Models\LedgerAccount;
use App\Domains\Financial\Models\LedgerTransaction;
use App\Domains\Financial\Models\LedgerEntry;
use App\Domains\Financial\Models\Settlement;
use App\Domains\Financial\Exceptions\LedgerUnbalancedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LedgerService
{
    /**
     * Post a balanced double-entry transaction atomically with idempotency protection.
     *
     * @param string $idempotencyKey Unique key preventing duplicate postings
     * @param string $referenceType Business operation type (e.g. 'booking_completion', 'cod_commission')
     * @param string|null $referenceId Entity ID (e.g. booking UUID)
     * @param array $entries Array of ['account_code' => string, 'direction' => 'debit'|'credit', 'amount' => float, 'description' => string]
     * @param string $description Narrative description of transaction
     * @param string|null $userId User or system ID triggering transaction
     * @return LedgerTransaction
     * @throws LedgerUnbalancedException
     */
    public function postTransaction(
        string $idempotencyKey,
        string $referenceType,
        ?string $referenceId,
        array $entries,
        string $description,
        ?string $userId = null
    ): LedgerTransaction {
        return DB::transaction(function () use ($idempotencyKey, $referenceType, $referenceId, $entries, $description, $userId) {
            // 1. Idempotency Check with pessimistic lock
            $existing = LedgerTransaction::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->status === 'posted') {
                    return $existing;
                }
            }

            // 2. Validate entry count
            if (count($entries) < 2) {
                throw new \InvalidArgumentException("Transaction must contain at least two entries (one debit and one credit).");
            }

            // 3. Validate Debit/Credit Balance
            $totalDebit = 0.00;
            $totalCredit = 0.00;

            foreach ($entries as $entry) {
                $amount = round((float) $entry['amount'], 2);
                if ($amount <= 0) {
                    throw new \InvalidArgumentException("Ledger entry amount must be strictly positive. Given: {$amount}");
                }

                if ($entry['direction'] === 'debit') {
                    $totalDebit += $amount;
                } elseif ($entry['direction'] === 'credit') {
                    $totalCredit += $amount;
                } else {
                    throw new \InvalidArgumentException("Invalid entry direction: '{$entry['direction']}'. Must be 'debit' or 'credit'.");
                }
            }

            $totalDebit = round($totalDebit, 2);
            $totalCredit = round($totalCredit, 2);
            $imbalance = round(abs($totalDebit - $totalCredit), 2);

            if ($imbalance > 0.001) {
                throw new LedgerUnbalancedException((string)$totalDebit, (string)$totalCredit, (string)$imbalance);
            }

            // 4. Resolve Account IDs
            $accountCodes = array_column($entries, 'account_code');
            $accounts = LedgerAccount::whereIn('account_code', $accountCodes)->get()->keyBy('account_code');

            foreach ($accountCodes as $code) {
                if (!isset($accounts[$code])) {
                    throw new \InvalidArgumentException("Ledger account with code '{$code}' not found.");
                }
                if (!$accounts[$code]->is_active) {
                    throw new \InvalidArgumentException("Ledger account '{$code}' is inactive.");
                }
            }

            // 5. Create Transaction Header in draft
            $txNumber = 'TX-' . date('Ymd') . '-' . strtoupper(Str::random(8));
            $transaction = LedgerTransaction::create([
                'transaction_number' => $txNumber,
                'idempotency_key' => $idempotencyKey,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'status' => 'draft',
                'created_by' => $userId,
            ]);

            // 6. Insert Entries
            foreach ($entries as $entry) {
                $account = $accounts[$entry['account_code']];
                LedgerEntry::create([
                    'ledger_transaction_id' => $transaction->id,
                    'ledger_account_id' => $account->id,
                    'direction' => $entry['direction'],
                    'amount' => round((float) $entry['amount'], 2),
                    'currency' => $account->currency ?? 'PKR',
                    'description' => $entry['description'] ?? $description,
                    'created_at' => now(),
                ]);
            }

            // 7. Transition to 'posted' (triggers DB level balance verification)
            $transaction->update([
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            return $transaction->fresh(['entries.account']);
        });
    }

    /**
     * Reverse an existing posted transaction with full double-entry inversion.
     */
    public function reverseTransaction(
        string $transactionId,
        string $reason,
        string $idempotencyKey,
        ?string $userId = null
    ): LedgerTransaction {
        return DB::transaction(function () use ($transactionId, $reason, $idempotencyKey, $userId) {
            $original = LedgerTransaction::with('entries.account')->where('id', $transactionId)->lockForUpdate()->firstOrFail();

            if ($original->status !== 'posted') {
                throw new \InvalidArgumentException("Only 'posted' transactions can be reversed. Current status: {$original->status}");
            }

            $reversalEntries = [];
            foreach ($original->entries as $entry) {
                $reversalEntries[] = [
                    'account_code' => $entry->account->account_code,
                    // Invert direction
                    'direction' => $entry->direction === 'debit' ? 'credit' : 'debit',
                    'amount' => (float) $entry->amount,
                    'description' => "Reversal of {$original->transaction_number}: " . ($entry->description ?? ''),
                ];
            }

            // Mark original as reversed
            $original->update(['status' => 'reversed']);

            // Post reversal transaction
            return $this->postTransaction(
                idempotencyKey: $idempotencyKey,
                referenceType: 'reversal',
                referenceId: $original->id,
                entries: $reversalEntries,
                description: "Reversal of {$original->transaction_number} - {$reason}",
                userId: $userId
            );
        });
    }

    /**
     * 1. Online Escrow Lock: Customer funds job online
     * Debit:  1010 Platform Bank/Gateway
     * Credit: 2010 Customer Escrow Holding
     */
    public function postOnlineEscrowHold(Booking $booking, float $amount, string $paymentRef, ?string $userId = null): LedgerTransaction
    {
        return $this->postTransaction(
            idempotencyKey: "ESCROW-LOCK-{$booking->id}-{$paymentRef}",
            referenceType: 'booking_escrow',
            referenceId: $booking->id,
            entries: [
                ['account_code' => '1010', 'direction' => 'debit', 'amount' => $amount, 'description' => "Escrow hold for Booking #{$booking->booking_number}"],
                ['account_code' => '2010', 'direction' => 'credit', 'amount' => $amount, 'description' => "Escrow holding liability for Booking #{$booking->booking_number}"],
            ],
            description: "Online payment captured in escrow for Booking #{$booking->booking_number}",
            userId: $userId
        );
    }

    /**
     * 2. Online Completion: Release Escrow to Provider Payable & Platform Commission
     * Debit:  2010 Customer Escrow Holding (Total)
     * Credit: 2020 Provider Payable (Total - Commission)
     * Credit: 4010 Platform Commission Revenue (Commission)
     */
    public function postOnlineCompletion(Booking $booking, float $totalAmount, float $commissionAmount, ?string $userId = null): LedgerTransaction
    {
        $providerNet = round($totalAmount - $commissionAmount, 2);

        return $this->postTransaction(
            idempotencyKey: "ONLINE-COMPLETE-{$booking->id}",
            referenceType: 'booking_completion',
            referenceId: $booking->id,
            entries: [
                ['account_code' => '2010', 'direction' => 'debit', 'amount' => $totalAmount, 'description' => "Release customer escrow for Booking #{$booking->booking_number}"],
                ['account_code' => '2020', 'direction' => 'credit', 'amount' => $providerNet, 'description' => "Earnings payable to Provider for Booking #{$booking->booking_number}"],
                ['account_code' => '4010', 'direction' => 'credit', 'amount' => $commissionAmount, 'description' => "Platform commission on Booking #{$booking->booking_number}"],
            ],
            description: "Online job completion earnings and commission for Booking #{$booking->booking_number}",
            userId: $userId
        );
    }

    /**
     * 3. COD Completion: Customer paid technician directly.
     * Platform earns commission debt from provider.
     * Debit:  1030 Provider Commission Receivable (Asset)
     * Credit: 4010 Platform Commission Revenue (Revenue)
     */
    public function postCodCompletion(Booking $booking, float $commissionAmount, ?string $userId = null): LedgerTransaction
    {
        return $this->postTransaction(
            idempotencyKey: "COD-COMPLETE-{$booking->id}",
            referenceType: 'cod_commission',
            referenceId: $booking->id,
            entries: [
                ['account_code' => '1030', 'direction' => 'debit', 'amount' => $commissionAmount, 'description' => "Commission receivable from Provider for COD Booking #{$booking->booking_number}"],
                ['account_code' => '4010', 'direction' => 'credit', 'amount' => $commissionAmount, 'description' => "Platform commission revenue earned on COD Booking #{$booking->booking_number}"],
            ],
            description: "COD job commission recognized for Booking #{$booking->booking_number}",
            userId: $userId
        );
    }

    /**
     * 3b. Provider Debt Payment: Provider settles their outstanding COD commission debt.
     * Debit:  1010 Platform Main Bank / Cash (Asset increased)
     * Credit: 1030 Provider Commission Receivable (Asset decreased - debt cleared)
     */
    public function postProviderDebtPayment(
        \App\Domains\Provider\Models\ProviderProfile $provider,
        float $amount,
        string $paymentRef,
        ?string $userId = null
    ): LedgerTransaction {
        return $this->postTransaction(
            idempotencyKey: "PROVIDER-DEBT-PAY-{$provider->id}-{$paymentRef}",
            referenceType: 'provider_debt_payment',
            referenceId: $provider->id,
            entries: [
                ['account_code' => '1010', 'direction' => 'debit', 'amount' => $amount, 'description' => "Inflow to Platform Bank clearing COD debt for Provider #{$provider->id}"],
                ['account_code' => '1030', 'direction' => 'credit', 'amount' => $amount, 'description' => "Clear outstanding COD commission receivable for Provider #{$provider->id}"],
            ],
            description: "Provider cleared COD commission debt via {$paymentRef}",
            userId: $userId
        );
    }

    /**
     * 3c. Automated Internal Clearing Offset:
     * Online earnings in 2020 offset outstanding COD commission debt in 1030.
     * Debit:  2020 Provider Payable (Liability reduced)
     * Credit: 1030 Provider Commission Receivable (Asset reduced - debt settled internally)
     */
    public function postInternalOffsetClearing(
        \App\Domains\Provider\Models\ProviderProfile $provider,
        float $offsetAmount,
        ?string $userId = null
    ): LedgerTransaction {
        return $this->postTransaction(
            idempotencyKey: "INTERNAL-OFFSET-{$provider->id}-" . date('YmdHis'),
            referenceType: 'internal_offset_clearing',
            referenceId: $provider->id,
            entries: [
                ['account_code' => '2020', 'direction' => 'debit', 'amount' => $offsetAmount, 'description' => "Offset online payable to settle COD commission debt for Provider #{$provider->id}"],
                ['account_code' => '1030', 'direction' => 'credit', 'amount' => $offsetAmount, 'description' => "Extinguish COD commission receivable via online earnings offset"],
            ],
            description: "Internal offset clearing between Provider Payable (2020) and Receivable (1030)",
            userId: $userId
        );
    }

    /**
     * 4. Customer Cancellation After Provider Dispatched:
     * Customer charged travel compensation fee (PKR 500), credited to Provider.
     * Debit:  2010 Customer Escrow Holding (or Customer Account)
     * Credit: 2020 Provider Payable
     */
    public function postCustomerCancellationWithDispatchFee(Booking $booking, float $travelFee = 500.00, ?string $userId = null): LedgerTransaction
    {
        return $this->postTransaction(
            idempotencyKey: "CANCEL-TRAVEL-FEE-{$booking->id}",
            referenceType: 'cancellation_dispatch_fee',
            referenceId: $booking->id,
            entries: [
                ['account_code' => '2010', 'direction' => 'debit', 'amount' => $travelFee, 'description' => "Cancellation dispatch charge against escrow for Booking #{$booking->booking_number}"],
                ['account_code' => '2020', 'direction' => 'credit', 'amount' => $travelFee, 'description' => "Travel compensation to Provider for Booking #{$booking->booking_number}"],
            ],
            description: "Customer late cancellation travel fee for Booking #{$booking->booking_number}",
            userId: $userId
        );
    }

    /**
     * 5. Provider Cancellation / No-Show Penalty:
     * Provider fined penalty fee.
     * Debit:  1030 Provider Commission Receivable (Asset - debt owed by provider)
     * Credit: 4030 Platform Penalty Fee Revenue (Revenue)
     */
    public function postProviderPenalty(Booking $booking, float $penaltyFee, string $reason, ?string $userId = null): LedgerTransaction
    {
        return $this->postTransaction(
            idempotencyKey: "PROVIDER-PENALTY-{$booking->id}-" . Str::slug($reason),
            referenceType: 'provider_penalty',
            referenceId: $booking->id,
            entries: [
                ['account_code' => '1030', 'direction' => 'debit', 'amount' => $penaltyFee, 'description' => "Penalty fine charged to Provider for {$reason} on Booking #{$booking->booking_number}"],
                ['account_code' => '4030', 'direction' => 'credit', 'amount' => $penaltyFee, 'description' => "Platform penalty fee revenue: {$reason}"],
            ],
            description: "Provider penalty for {$reason} on Booking #{$booking->booking_number}",
            userId: $userId
        );
    }

    /**
     * 6. Provider Settlement Payout:
     * Platform transfers accumulated earnings to provider's bank account.
     * Debit:  2020 Provider Payable (Liability decreased)
     * Credit: 1010 Platform Bank Account (Asset decreased)
     */
    public function postSettlementPayout(Settlement $settlement, ?string $userId = null): LedgerTransaction
    {
        return $this->postTransaction(
            idempotencyKey: "SETTLEMENT-PAYOUT-{$settlement->id}",
            referenceType: 'settlement_payout',
            referenceId: $settlement->id,
            entries: [
                ['account_code' => '2020', 'direction' => 'debit', 'amount' => (float)$settlement->amount, 'description' => "Settlement payout to Provider for Batch #{$settlement->settlement_number}"],
                ['account_code' => '1010', 'direction' => 'credit', 'amount' => (float)$settlement->amount, 'description' => "Bank disbursement for Settlement #{$settlement->settlement_number}"],
            ],
            description: "Provider settlement payout batch #{$settlement->settlement_number}",
            userId: $userId
        );
    }

    /**
     * 7. Customer Pre-Dispatch Cancellation Full Refund:
     * 100% refund of escrow funds to customer.
     * Debit:  2010 Customer Escrow Holding (Liability decreased)
     * Credit: 1010 Platform Bank/Cash (Asset decreased via gateway refund)
     */
    public function postCustomerPreDispatchRefund(Booking $booking, float $amount, ?string $userId = null): LedgerTransaction
    {
        return $this->postTransaction(
            idempotencyKey: "REFUND-PREDISPATCH-{$booking->id}",
            referenceType: 'customer_refund_predispatch',
            referenceId: $booking->id,
            entries: [
                ['account_code' => '2010', 'direction' => 'debit', 'amount' => $amount, 'description' => "Pre-dispatch cancellation full refund for Booking #{$booking->booking_number}"],
                ['account_code' => '1010', 'direction' => 'credit', 'amount' => $amount, 'description' => "Gateway refund disbursement to customer"],
            ],
            description: "Full refund prior to dispatch for Booking #{$booking->booking_number}",
            userId: $userId
        );
    }

    /**
     * 8. Customer No-Show Settlement:
     * Provider arrives on site, waits > 20 mins, calls unresponded.
     * Travel compensation (PKR 500) credited to Provider Payable; remaining escrow refunded to customer.
     * Debit:  2010 Customer Escrow Holding (Total)
     * Credit: 2020 Provider Payable (Travel Fee)
     * Credit: 1010 Platform Bank/Cash (Customer Refund)
     */
    public function postCustomerNoShowSettlement(Booking $booking, float $totalEscrow, float $travelFee, ?string $userId = null): LedgerTransaction
    {
        $customerRefund = round(max(0.00, $totalEscrow - $travelFee), 2);

        $entries = [
            ['account_code' => '2010', 'direction' => 'debit', 'amount' => $totalEscrow, 'description' => "Release customer escrow for Customer No-Show Booking #{$booking->booking_number}"],
            ['account_code' => '2020', 'direction' => 'credit', 'amount' => $travelFee, 'description' => "Travel compensation to Provider for Customer No-Show"],
        ];

        if ($customerRefund > 0.00) {
            $entries[] = ['account_code' => '1010', 'direction' => 'credit', 'amount' => $customerRefund, 'description' => "Refund remainder of escrow to customer"];
        }

        return $this->postTransaction(
            idempotencyKey: "NOSHOW-CUSTOMER-{$booking->id}",
            referenceType: 'customer_no_show_settlement',
            referenceId: $booking->id,
            entries: $entries,
            description: "Customer no-show settlement for Booking #{$booking->booking_number}",
            userId: $userId
        );
    }

    /**
     * 9. Provider No-Show Customer Full Refund:
     * Provider failed to arrive within grace window. Customer receives 100% refund.
     * Debit:  2010 Customer Escrow Holding (Total)
     * Credit: 1010 Platform Bank/Cash (Total)
     */
    public function postProviderNoShowCustomerRefund(Booking $booking, float $totalEscrow, ?string $userId = null): LedgerTransaction
    {
        return $this->postTransaction(
            idempotencyKey: "NOSHOW-PROVIDER-REFUND-{$booking->id}",
            referenceType: 'provider_no_show_customer_refund',
            referenceId: $booking->id,
            entries: [
                ['account_code' => '2010', 'direction' => 'debit', 'amount' => $totalEscrow, 'description' => "Full refund due to Provider No-Show for Booking #{$booking->booking_number}"],
                ['account_code' => '1010', 'direction' => 'credit', 'amount' => $totalEscrow, 'description' => "Gateway refund disbursement to customer"],
            ],
            description: "Provider no-show customer full refund for Booking #{$booking->booking_number}",
            userId: $userId
        );
    }
}
