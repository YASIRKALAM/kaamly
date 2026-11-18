<?php

namespace App\Domains\Provider\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Provider\Models\ProviderProfile;
use App\Domains\Provider\Models\ProviderDocument;
use App\Domains\Provider\Models\ProviderBankAccount;
use App\Domains\Provider\Models\ProviderWorkingHour;
use App\Domains\Provider\Models\ProviderStatusHistory;
use App\Domains\Service\Models\Service;
use App\Domains\Service\Models\ProviderService;
use App\Domains\Service\Models\ProviderServicePrice;
use App\Domains\Service\Models\PriceApprovalHistory;
use App\Domains\Shared\Services\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProviderOnboardingService
{
    protected StorageService $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Step 1: Update basic business profile
     */
    public function updateProfile(ProviderProfile $profile, array $data): ProviderProfile
    {
        $profile->update([
            'business_name' => $data['business_name'] ?? $profile->business_name,
            'cnic_number' => $data['cnic_number'] ?? $profile->cnic_number,
            'cnic_encrypted' => isset($data['cnic_number']) ? encrypt($data['cnic_number']) : $profile->cnic_encrypted,
            'onboarding_step' => max($profile->onboarding_step, 2),
        ]);

        return $profile->fresh();
    }

    /**
     * Step 2: Upload sensitive identity/compliance documents
     */
    public function uploadDocument(
        ProviderProfile $profile,
        string $documentType,
        UploadedFile $file,
        ?string $documentNumber = null
    ): ProviderDocument {
        $uploadMeta = $this->storageService->storePrivateDocument($file, "providers/{$profile->id}/documents");

        return ProviderDocument::updateOrCreate(
            [
                'provider_profile_id' => $profile->id,
                'document_type' => $documentType,
            ],
            [
                'document_number' => $documentNumber,
                'file_path_s3' => $uploadMeta['file_path_s3'],
                'file_mime_type' => $uploadMeta['file_mime_type'],
                'verification_status' => 'pending',
                'rejection_reason' => null,
            ]
        );
    }

    /**
     * Step 3: Configure Services & Custom Pricing
     */
    public function configureServices(ProviderProfile $profile, array $servicesList): void
    {
        DB::transaction(function () use ($profile, $servicesList) {
            foreach ($servicesList as $item) {
                $service = Service::findOrFail($item['service_id']);
                $customPrice = (float) $item['custom_price'];

                // Validate within bounds
                if ($customPrice < (float)$service->minimum_price || $customPrice > (float)$service->maximum_price) {
                    throw new InvalidArgumentException(
                        "Custom price PKR {$customPrice} for service '{$service->name}' is outside allowed bounds [{$service->minimum_price} - {$service->maximum_price}]."
                    );
                }

                $ps = ProviderService::firstOrCreate([
                    'provider_profile_id' => $profile->id,
                    'service_id' => $service->id,
                ], ['is_active' => true]);

                $price = ProviderServicePrice::create([
                    'provider_service_id' => $ps->id,
                    'custom_price' => $customPrice,
                    'approval_status' => 'pending',
                    'is_current' => false,
                    'effective_from' => now(),
                ]);

                PriceApprovalHistory::create([
                    'provider_service_price_id' => $price->id,
                    'requested_price' => $customPrice,
                    'previous_price' => null,
                    'action' => 'requested',
                    'created_at' => now(),
                ]);
            }

            $profile->update(['onboarding_step' => max($profile->onboarding_step, 3)]);
        });
    }

    /**
     * Step 4: Configure Weekly Working Hours
     */
    public function setWorkingHours(ProviderProfile $profile, array $schedule): void
    {
        DB::transaction(function () use ($profile, $schedule) {
            // Remove existing working hours to reset
            ProviderWorkingHour::where('provider_profile_id', $profile->id)->delete();

            foreach ($schedule as $slot) {
                ProviderWorkingHour::create([
                    'provider_profile_id' => $profile->id,
                    'day_of_week' => (int) $slot['day_of_week'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'is_active' => $slot['is_active'] ?? true,
                ]);
            }

            $profile->update(['onboarding_step' => max($profile->onboarding_step, 4)]);
        });
    }

    /**
     * Step 5: Add Bank Account
     */
    public function setBankAccount(ProviderProfile $profile, array $bankData): ProviderBankAccount
    {
        return DB::transaction(function () use ($profile, $bankData) {
            if (!empty($bankData['is_primary'])) {
                ProviderBankAccount::where('provider_profile_id', $profile->id)->update(['is_primary' => false]);
            }

            return ProviderBankAccount::create([
                'provider_profile_id' => $profile->id,
                'bank_name' => $bankData['bank_name'],
                'account_title' => $bankData['account_title'],
                'account_number_encrypted' => encrypt($bankData['account_number']),
                'iban_encrypted' => encrypt($bankData['iban']),
                'is_verified' => false,
                'is_primary' => $bankData['is_primary'] ?? true,
            ]);
        });
    }

    /**
     * Submit profile for administrative review & approval
     */
    public function submitForReview(ProviderProfile $profile): ProviderProfile
    {
        // Check prerequisites
        $hasCnic = ProviderDocument::where('provider_profile_id', $profile->id)
            ->whereIn('document_type', ['cnic_front', 'cnic_back'])
            ->count() >= 2;

        if (!$hasCnic) {
            throw new InvalidArgumentException("Both CNIC front and back document uploads are required before submitting for review.");
        }

        $hasService = ProviderService::where('provider_profile_id', $profile->id)->exists();
        if (!$hasService) {
            throw new InvalidArgumentException("At least one service offering must be selected.");
        }

        $hasWorkingHours = ProviderWorkingHour::where('provider_profile_id', $profile->id)->where('is_active', true)->exists();
        if (!$hasWorkingHours) {
            throw new InvalidArgumentException("Active working hours must be defined.");
        }

        $hasBank = ProviderBankAccount::where('provider_profile_id', $profile->id)->exists();
        if (!$hasBank) {
            throw new InvalidArgumentException("Bank account details must be configured for payout settlements.");
        }

        $prevStatus = $profile->status;
        $profile->update([
            'status' => 'pending_approval',
            'rejection_reason' => null,
        ]);

        ProviderStatusHistory::create([
            'provider_profile_id' => $profile->id,
            'previous_status' => $prevStatus,
            'new_status' => 'pending_approval',
            'reason' => 'Provider submitted all onboarding requirements for review',
            'created_at' => now(),
        ]);

        return $profile->fresh();
    }

    /**
     * Admin: Review and verify/reject document
     */
    public function reviewDocument(
        ProviderDocument $document,
        string $status,
        ?string $rejectionReason,
        User $admin
    ): ProviderDocument {
        if (!in_array($status, ['verified', 'rejected'])) {
            throw new InvalidArgumentException("Status must be 'verified' or 'rejected'.");
        }

        $document->update([
            'verification_status' => $status,
            'verified_by' => $admin->id,
            'verified_at' => now(),
            'rejection_reason' => $status === 'rejected' ? $rejectionReason : null,
        ]);

        return $document;
    }

    /**
     * Admin: Approve Provider Profile
     */
    public function approveProvider(ProviderProfile $profile, User $admin): ProviderProfile
    {
        return DB::transaction(function () use ($profile, $admin) {
            $prevStatus = $profile->status;

            // Approve all pending custom prices
            $pendingPrices = ProviderServicePrice::whereHas('providerService', fn($q) => $q->where('provider_profile_id', $profile->id))
                ->where('approval_status', 'pending')
                ->get();

            foreach ($pendingPrices as $price) {
                // Set current
                ProviderServicePrice::where('provider_service_id', $price->provider_service_id)->update(['is_current' => false]);
                $price->update([
                    'approval_status' => 'approved',
                    'approved_by' => $admin->id,
                    'approved_at' => now(),
                    'is_current' => true,
                ]);

                PriceApprovalHistory::create([
                    'provider_service_price_id' => $price->id,
                    'requested_price' => $price->custom_price,
                    'previous_price' => null,
                    'action' => 'approved',
                    'reviewed_by' => $admin->id,
                    'created_at' => now(),
                ]);
            }

            // Verify Primary Bank Account
            ProviderBankAccount::where('provider_profile_id', $profile->id)->update([
                'is_verified' => true,
                'verified_by' => $admin->id,
                'verified_at' => now(),
            ]);

            $profile->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $admin->id,
                'is_available' => true,
            ]);

            ProviderStatusHistory::create([
                'provider_profile_id' => $profile->id,
                'previous_status' => $prevStatus,
                'new_status' => 'approved',
                'reason' => 'All documents verified and onboarding completed.',
                'changed_by' => $admin->id,
                'created_at' => now(),
            ]);

            return $profile->fresh();
        });
    }
}
