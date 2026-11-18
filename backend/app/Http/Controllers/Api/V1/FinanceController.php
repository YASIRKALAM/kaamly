<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domains\Financial\Models\LedgerAccount;
use App\Domains\Financial\Models\LedgerTransaction;
use App\Domains\Financial\Models\Settlement;
use App\Domains\Financial\Services\LedgerService;
use App\Domains\Provider\Models\ProviderProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinanceController extends Controller
{
    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Chart of Accounts with live computed balances
     */
    public function chartOfAccounts(): JsonResponse
    {
        $accounts = LedgerAccount::where('is_active', true)->orderBy('account_code')->get();

        $data = $accounts->map(function ($acc) {
            return [
                'id' => $acc->id,
                'account_code' => $acc->account_code,
                'account_name' => $acc->account_name,
                'account_type' => $acc->account_type,
                'currency' => $acc->currency,
                'current_balance' => $acc->getBalance(),
                'description' => $acc->description,
            ];
        });

        return $this->successResponse($data);
    }

    /**
     * List Ledger Transactions with entries
     */
    public function ledgerTransactions(Request $request): JsonResponse
    {
        $transactions = LedgerTransaction::with(['entries.account', 'createdByUser'])
            ->latest('posted_at')
            ->paginate(20);

        return $this->successResponse($transactions);
    }

    /**
     * Manual Transaction Reversal with Maker-Checker Dual-Control Verification
     */
    public function reverseTransaction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|uuid|exists:ledger_transactions,id',
            'reason' => 'required|string|min:5|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid reversal parameters.', $validator->errors(), 422);
        }

        $original = LedgerTransaction::findOrFail($request->input('transaction_id'));

        // Maker-Checker enforcement: the user who created/posted the transaction cannot reverse it
        if ($original->created_by && $original->created_by === $request->user()->id) {
            return $this->errorResponse(
                'MAKER_CHECKER_VIOLATION',
                'Dual-control maker-checker violation: You cannot reverse a transaction that you originally created.',
                null,
                403
            );
        }

        $idempotencyKey = 'REV-' . Str::uuid();

        $reversal = $this->ledgerService->reverseTransaction(
            transactionId: $original->id,
            reason: $request->input('reason'),
            idempotencyKey: $idempotencyKey,
            userId: $request->user()->id
        );

        return $this->successResponse($reversal, 'Transaction successfully reversed.');
    }

    /**
     * List settlements
     */
    public function listSettlements(Request $request): JsonResponse
    {
        $settlements = Settlement::with(['providerProfile.user', 'bankAccount', 'items'])
            ->latest('created_at')
            ->paginate(20);

        return $this->successResponse($settlements);
    }

    /**
     * Create Settlement Batch for a Provider
     */
    public function createSettlementBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider_profile_id' => 'required|uuid|exists:provider_profiles,id',
            'amount' => 'required|numeric|min:500',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid batch parameters.', $validator->errors(), 422);
        }

        $provider = ProviderProfile::with('primaryBankAccount')->findOrFail($request->input('provider_profile_id'));
        if (!$provider->primaryBankAccount) {
            return $this->errorResponse('NO_BANK_ACCOUNT', 'Provider does not have a verified bank account on file.', null, 422);
        }

        // Prevent settlement if provider has active unresolved disputes
        $hasOpenDisputes = DB::table('disputes')
            ->join('bookings', 'disputes.booking_id', '=', 'bookings.id')
            ->where('bookings.provider_profile_id', $provider->id)
            ->where('disputes.dispute_status', 'opened')
            ->exists();

        if ($hasOpenDisputes) {
            return $this->errorResponse(
                'DISPUTE_HOLD',
                'Cannot create settlement batch. Provider has active unresolved disputes that must be arbitrated first.',
                null,
                422
            );
        }

        // Validate provider available payable balance
        $payableAccount = LedgerAccount::where('account_code', '2020')->first();
        $receivableAccount = LedgerAccount::where('account_code', '1030')->first();

        $providerBookings = DB::table('bookings')->where('provider_profile_id', $provider->id)->pluck('id');
        $earnedPayables = 0.00;
        $codDebt = 0.00;

        if ($providerBookings->isNotEmpty()) {
            if ($payableAccount) {
                $payRes = DB::table('ledger_entries')
                    ->join('ledger_transactions', 'ledger_entries.ledger_transaction_id', '=', 'ledger_transactions.id')
                    ->where('ledger_entries.ledger_account_id', $payableAccount->id)
                    ->where('ledger_transactions.status', 'posted')
                    ->whereIn('ledger_transactions.reference_id', $providerBookings)
                    ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) as balance")
                    ->first();
                $earnedPayables = max(0, (float) $payRes->balance);
            }
            if ($receivableAccount) {
                $debtRes = DB::table('ledger_entries')
                    ->join('ledger_transactions', 'ledger_entries.ledger_transaction_id', '=', 'ledger_transactions.id')
                    ->where('ledger_entries.ledger_account_id', $receivableAccount->id)
                    ->where('ledger_transactions.status', 'posted')
                    ->whereIn('ledger_transactions.reference_id', $providerBookings)
                    ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END), 0) as debt")
                    ->first();
                $codDebt = max(0, (float) $debtRes->debt);
            }
        }

        $pendingSettlementSum = (float) DB::table('settlements')
            ->where('provider_profile_id', $provider->id)
            ->where('status', 'pending')
            ->sum('amount');

        $netWithdrawable = max(0.00, $earnedPayables - $codDebt - $pendingSettlementSum);
        $requestedAmount = round((float)$request->input('amount'), 2);

        if ($requestedAmount > $netWithdrawable) {
            return $this->errorResponse(
                'INSUFFICIENT_PAYABLE_BALANCE',
                "Requested settlement amount (PKR {$requestedAmount}) exceeds available net withdrawable balance (PKR {$netWithdrawable}).",
                [
                    'requested_amount' => $requestedAmount,
                    'earned_payables' => $earnedPayables,
                    'cod_debt' => $codDebt,
                    'pending_settlements' => $pendingSettlementSum,
                    'net_withdrawable' => $netWithdrawable,
                ],
                422
            );
        }

        $settlement = Settlement::create([
            'settlement_number' => 'SETTLE-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
            'provider_profile_id' => $provider->id,
            'provider_bank_account_id' => $provider->primaryBankAccount->id,
            'amount' => $requestedAmount,
            'currency' => 'PKR',
            'status' => 'pending',
        ]);

        return $this->successResponse($settlement, 'Settlement batch generated.', [], 201);
    }

    /**
     * Process Settlement and Post to Ledger
     */
    public function processSettlement(Request $request, string $id): JsonResponse
    {
        $settlement = Settlement::findOrFail($id);

        if ($settlement->status === 'completed') {
            return $this->errorResponse('ALREADY_PROCESSED', 'This settlement has already been completed.', null, 422);
        }

        $payoutRef = $request->input('payout_reference', 'IBFT-' . strtoupper(Str::random(10)));

        DB::transaction(function () use ($settlement, $payoutRef, $request) {
            $settlement->update([
                'status' => 'completed',
                'payout_reference' => $payoutRef,
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
            ]);

            // Post double-entry settlement transaction:
            // Debit:  2020 Provider Payable
            // Credit: 1010 Platform Bank Account
            $this->ledgerService->postSettlementPayout($settlement, $request->user()->id);
        });

        return $this->successResponse($settlement->fresh(), 'Settlement successfully disbursed and ledger updated.');
    }
}
