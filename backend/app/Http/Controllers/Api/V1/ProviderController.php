<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domains\Financial\Models\LedgerAccount;
use App\Domains\Provider\Models\ProviderProfile;
use App\Domains\Provider\Models\ProviderDocument;
use App\Domains\Provider\Services\ProviderOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ProviderController extends Controller
{
    protected ProviderOnboardingService $onboardingService;

    public function __construct(ProviderOnboardingService $onboardingService)
    {
        $this->onboardingService = $onboardingService;
    }

    /**
     * Step 1: Update Onboarding Profile
     */
    public function updateOnboardingProfile(Request $request): JsonResponse
    {
        $profile = $request->user()->providerProfile;
        if (!$profile) {
            return $this->errorResponse('PROFILE_NOT_FOUND', 'Provider profile not found.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'business_name' => 'required|string|max:150',
            'cnic_number' => 'required|string|max:20|unique:provider_profiles,cnic_number,' . $profile->id,
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Validation failed.', $validator->errors(), 422);
        }

        $updated = $this->onboardingService->updateProfile($profile, $validator->validated());

        return $this->successResponse($updated, 'Profile updated.');
    }

    /**
     * Step 2: Upload Compliance Document
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $profile = $request->user()->providerProfile;
        if (!$profile) {
            return $this->errorResponse('PROFILE_NOT_FOUND', 'Provider profile not found.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'document_type' => 'required|in:cnic_front,cnic_back,police_clearance,trade_license,certification',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', // 10MB max
            'document_number' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Document upload failed validation.', $validator->errors(), 422);
        }

        $document = $this->onboardingService->uploadDocument(
            profile: $profile,
            documentType: $request->input('document_type'),
            file: $request->file('file'),
            documentNumber: $request->input('document_number')
        );

        return $this->successResponse($document, 'Document uploaded securely.', [], 201);
    }

    /**
     * Step 3: Configure Services & Custom Pricing
     */
    public function configureServices(Request $request): JsonResponse
    {
        $profile = $request->user()->providerProfile;
        if (!$profile) {
            return $this->errorResponse('PROFILE_NOT_FOUND', 'Provider profile not found.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'services' => 'required|array|min:1',
            'services.*.service_id' => 'required|uuid|exists:services,id',
            'services.*.custom_price' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Service selection failed validation.', $validator->errors(), 422);
        }

        $this->onboardingService->configureServices($profile, $request->input('services'));

        return $this->successResponse(null, 'Services and prices configured.');
    }

    /**
     * Step 4: Configure Weekly Working Hours
     */
    public function setWorkingHours(Request $request): JsonResponse
    {
        $profile = $request->user()->providerProfile;
        if (!$profile) {
            return $this->errorResponse('PROFILE_NOT_FOUND', 'Provider profile not found.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'schedule' => 'required|array|min:1',
            'schedule.*.day_of_week' => 'required|integer|between:0,6',
            'schedule.*.start_time' => 'required|date_format:H:i',
            'schedule.*.end_time' => 'required|date_format:H:i|after:schedule.*.start_time',
            'schedule.*.is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid schedule format.', $validator->errors(), 422);
        }

        $this->onboardingService->setWorkingHours($profile, $request->input('schedule'));

        return $this->successResponse(null, 'Working hours saved.');
    }

    /**
     * Step 5: Configure Bank Account
     */
    public function setBankAccount(Request $request): JsonResponse
    {
        $profile = $request->user()->providerProfile;
        if (!$profile) {
            return $this->errorResponse('PROFILE_NOT_FOUND', 'Provider profile not found.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:100',
            'account_title' => 'required|string|max:150',
            'account_number' => 'required|string|max:50',
            'iban' => 'required|string|max:34',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid bank account details.', $validator->errors(), 422);
        }

        $bankAccount = $this->onboardingService->setBankAccount($profile, $validator->validated());

        return $this->successResponse([
            'id' => $bankAccount->id,
            'bank_name' => $bankAccount->bank_name,
            'account_title' => $bankAccount->account_title,
            'is_verified' => $bankAccount->is_verified,
        ], 'Bank account configured.', [], 201);
    }

    /**
     * Final: Submit for Review
     */
    public function submitForReview(Request $request): JsonResponse
    {
        $profile = $request->user()->providerProfile;
        if (!$profile) {
            return $this->errorResponse('PROFILE_NOT_FOUND', 'Provider profile not found.', null, 404);
        }

        $submitted = $this->onboardingService->submitForReview($profile);

        return $this->successResponse($submitted, 'Onboarding submitted for administrative verification.');
    }

    /**
     * Admin: Review Document
     */
    public function adminReviewDocument(Request $request, string $providerId, string $docId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:verified,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid review action.', $validator->errors(), 422);
        }

        $document = ProviderDocument::where('provider_profile_id', $providerId)->where('id', $docId)->firstOrFail();

        $reviewed = $this->onboardingService->reviewDocument(
            document: $document,
            status: $request->input('status'),
            rejectionReason: $request->input('rejection_reason'),
            admin: $request->user()
        );

        return $this->successResponse($reviewed, 'Document review recorded.');
    }

    /**
     * Admin: Approve Provider
     */
    public function adminApproveProvider(Request $request, string $providerId): JsonResponse
    {
        $profile = ProviderProfile::findOrFail($providerId);

        $approved = $this->onboardingService->approveProvider($profile, $request->user());

        return $this->successResponse($approved, 'Provider successfully approved and activated.');
    }

    /**
     * Update Provider live GPS location
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $profile = $request->user()->providerProfile;
        if (!$profile) {
            return $this->errorResponse('PROFILE_NOT_FOUND', 'Provider profile not found.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid coordinates.', $validator->errors(), 422);
        }

        $profile->updateLocation(
            (float) $request->input('latitude'),
            (float) $request->input('longitude')
        );

        return $this->successResponse([
            'provider_id' => $profile->id,
            'location_updated_at' => $profile->location_updated_at->toISOString(),
        ], 'Location updated successfully.');
    }

    /**
     * Toggle Provider Online / Shift Availability
     */
    public function setAvailability(Request $request): JsonResponse
    {
        $profile = $request->user()->providerProfile;
        if (!$profile) {
            return $this->errorResponse('PROFILE_NOT_FOUND', 'Provider profile not found.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'is_online' => 'nullable|boolean',
            'is_available' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid availability payload.', $validator->errors(), 422);
        }

        $updates = [];
        if ($request->has('is_online')) {
            $updates['is_online'] = $request->boolean('is_online');
        }
        if ($request->has('is_available')) {
            $updates['is_available'] = $request->boolean('is_available');
        }

        $profile->update($updates);

        return $this->successResponse([
            'is_online' => $profile->is_online,
            'is_available' => $profile->is_available,
        ], 'Availability updated successfully.');
    }

    /**
     * Provider Wallet Summary
     */
    public function walletSummary(Request $request): JsonResponse
    {
        $profile = $request->user()->providerProfile;
        if (!$profile) {
            return $this->errorResponse('PROFILE_NOT_FOUND', 'Provider profile not found.', null, 404);
        }

        $payableAccount = LedgerAccount::where('account_code', '2020')->first();
        $receivableAccount = LedgerAccount::where('account_code', '1030')->first();

        $providerBookings = DB::table('bookings')->where('provider_profile_id', $profile->id)->pluck('id');

        $payableBalance = 0.00;
        if ($payableAccount && $providerBookings->isNotEmpty()) {
            $payableRes = DB::table('ledger_entries')
                ->join('ledger_transactions', 'ledger_entries.ledger_transaction_id', '=', 'ledger_transactions.id')
                ->where('ledger_entries.ledger_account_id', $payableAccount->id)
                ->where('ledger_transactions.status', 'posted')
                ->whereIn('ledger_transactions.reference_id', $providerBookings)
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) as balance")
                ->first();
            $payableBalance = max(0, (float) $payableRes->balance);
        }

        $receivableBalance = 0.00;
        if ($receivableAccount && $providerBookings->isNotEmpty()) {
            $receivableRes = DB::table('ledger_entries')
                ->join('ledger_transactions', 'ledger_entries.ledger_transaction_id', '=', 'ledger_transactions.id')
                ->where('ledger_entries.ledger_account_id', $receivableAccount->id)
                ->where('ledger_transactions.status', 'posted')
                ->whereIn('ledger_transactions.reference_id', $providerBookings)
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END), 0) as balance")
                ->first();
            $receivableBalance = max(0, (float) $receivableRes->balance);
        }

        $netWithdrawable = round($payableBalance - $receivableBalance, 2);
        $isDebtBlocked = $receivableBalance >= 2000.00;

        return $this->successResponse([
            'currency' => 'PKR',
            'earned_payables' => $payableBalance,
            'outstanding_cod_commission_debt' => $receivableBalance,
            'net_withdrawable_balance' => $netWithdrawable,
            'cod_debt_threshold' => 2000.00,
            'is_debt_blocked' => $isDebtBlocked,
            'can_accept_jobs' => !$isDebtBlocked,
            'is_settlement_eligible' => ($netWithdrawable >= 1000.00 && !$isDebtBlocked),
        ]);
    }
}
