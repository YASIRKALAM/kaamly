<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingItem;
use App\Domains\Booking\Models\Quotation;
use App\Domains\Booking\Models\QuotationItem;
use App\Domains\Booking\Services\BookingStateMachineService;
use App\Domains\Booking\Services\BookingLifecycleService;
use App\Domains\Financial\Services\LedgerService;
use App\Domains\Customer\Models\CustomerAddress;
use App\Domains\Service\Models\Service;
use App\Domains\Provider\Models\ProviderProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    protected BookingStateMachineService $stateMachine;
    protected BookingLifecycleService $lifecycleService;
    protected LedgerService $ledgerService;

    public function __construct(
        BookingStateMachineService $stateMachine,
        BookingLifecycleService $lifecycleService,
        LedgerService $ledgerService
    ) {
        $this->stateMachine = $stateMachine;
        $this->lifecycleService = $lifecycleService;
        $this->ledgerService = $ledgerService;
    }

    /**
     * Customer: Create a new booking
     */
    public function createBooking(Request $request): JsonResponse
    {
        $customer = $request->user()->customerProfile;
        if (!$customer) {
            return $this->errorResponse('PROFILE_NOT_FOUND', 'Customer profile not found.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'service_id' => 'required|uuid|exists:services,id',
            'address_id' => 'required|uuid|exists:customer_addresses,id',
            'provider_profile_id' => 'nullable|uuid|exists:provider_profiles,id',
            'scheduled_start_at' => 'required|date|after:now',
            'payment_mode' => 'required|in:online_escrow,cash_on_delivery',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid booking details.', $validator->errors(), 422);
        }

        $validated = $validator->validated();

        // Verify address ownership
        $address = CustomerAddress::where('id', $validated['address_id'])
            ->where('customer_profile_id', $customer->id)
            ->first();

        if (!$address) {
            return $this->errorResponse('ADDRESS_OWNERSHIP_ERROR', 'You cannot create a booking with an address that does not belong to your profile.', null, 403);
        }

        $service = Service::findOrFail($validated['service_id']);
        $durationMinutes = $service->estimated_duration_minutes ?? 60;
        $scheduledStart = new \DateTime($validated['scheduled_start_at']);
        $scheduledEnd = (clone $scheduledStart)->modify("+{$durationMinutes} minutes");

        $booking = DB::transaction(function () use ($customer, $service, $validated, $scheduledStart, $scheduledEnd) {
            $bookingNumber = 'BK-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            $newBooking = Booking::create([
                'booking_number' => $bookingNumber,
                'customer_profile_id' => $customer->id,
                'provider_profile_id' => $validated['provider_profile_id'] ?? null,
                'address_id' => $validated['address_id'],
                'service_id' => $service->id,
                'status' => 'pending',
                'payment_mode' => $validated['payment_mode'],
                'payment_status' => 'pending',
                'scheduled_start_at' => $scheduledStart,
                'scheduled_end_at' => $scheduledEnd,
                'base_price' => $service->base_price,
                'agreed_total_price' => $service->base_price,
                'commission_rate' => 10.00,
                'platform_fee' => round($service->base_price * 0.10, 2),
            ]);

            BookingItem::create([
                'booking_id' => $newBooking->id,
                'service_id' => $service->id,
                'description' => $service->name,
                'unit_price' => $service->base_price,
                'quantity' => 1,
                'total_price' => $service->base_price,
            ]);

            return $newBooking;
        });

        return $this->successResponse($booking->load(['service', 'address']), 'Booking successfully created.', [], 201);
    }

    /**
     * Provider: Accept Job
     */
    public function acceptJob(Request $request, string $id): JsonResponse
    {
        $provider = $request->user()->providerProfile;
        if (!$provider) {
            return $this->errorResponse('PROFILE_NOT_FOUND', 'Provider profile not found.', null, 404);
        }

        $booking = Booking::findOrFail($id);

        if ($booking->provider_profile_id && $booking->provider_profile_id !== $provider->id) {
            return $this->errorResponse('FORBIDDEN', 'This job was assigned to another provider.', null, 403);
        }

        // Check COD Commission Debt Threshold
        $debtLimit = (float) config('marketplace.cod_debt_threshold', 2000.00);
        $receivableAccount = \App\Domains\Financial\Models\LedgerAccount::where('account_code', '1030')->first();
        if ($receivableAccount) {
            $providerBookings = DB::table('bookings')->where('provider_profile_id', $provider->id)->pluck('id');
            if ($providerBookings->isNotEmpty()) {
                $debtRes = DB::table('ledger_entries')
                    ->join('ledger_transactions', 'ledger_entries.ledger_transaction_id', '=', 'ledger_transactions.id')
                    ->where('ledger_entries.ledger_account_id', $receivableAccount->id)
                    ->where('ledger_transactions.status', 'posted')
                    ->whereIn('ledger_transactions.reference_id', $providerBookings)
                    ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END), 0) as debt")
                    ->first();
                $currentDebt = max(0, (float) $debtRes->debt);
                if ($currentDebt >= $debtLimit) {
                    return $this->errorResponse(
                        'DEBT_LIMIT_EXCEEDED',
                        "Cannot accept new jobs. You have PKR {$currentDebt} in outstanding COD commission debt exceeding the PKR {$debtLimit} limit. Please clear your balance.",
                        ['outstanding_debt' => $currentDebt, 'threshold' => $debtLimit],
                        403
                    );
                }
            }
        }

        $booking->update(['provider_profile_id' => $provider->id]);

        $updated = $this->stateMachine->transition(
            booking: $booking,
            targetStatus: 'accepted',
            actorRole: 'provider',
            userId: $request->user()->id,
            reason: 'Provider accepted job'
        );

        return $this->successResponse($updated, 'Job accepted successfully.');
    }

    /**
     * Customer / System: Confirm Booking
     */
    public function confirmBooking(Request $request, string $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);

        // If online_escrow, trigger escrow lock ledger posting
        if ($booking->payment_mode === 'online_escrow') {
            $paymentRef = $request->input('payment_reference', 'SIMULATED_ESCROW_' . Str::random(10));
            $this->ledgerService->postOnlineEscrowHold($booking, (float)$booking->agreed_total_price, $paymentRef, $request->user()->id);
            $booking->update(['payment_status' => 'escrow_locked']);
        }

        $updated = $this->stateMachine->transition(
            booking: $booking,
            targetStatus: 'confirmed',
            actorRole: 'customer',
            userId: $request->user()->id,
            reason: 'Booking confirmed with payment guarantee'
        );

        return $this->successResponse($updated, 'Booking confirmed.');
    }

    /**
     * Provider: On the Way
     */
    public function onTheWay(Request $request, string $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);

        $updated = $this->stateMachine->transition(
            booking: $booking,
            targetStatus: 'provider_on_the_way',
            actorRole: 'provider',
            userId: $request->user()->id,
            reason: 'Provider dispatched and en route to customer location'
        );

        return $this->successResponse($updated, 'Status updated to en route.');
    }

    /**
     * Provider: Start Job
     */
    public function startJob(Request $request, string $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);

        $updated = $this->stateMachine->transition(
            booking: $booking,
            targetStatus: 'in_progress',
            actorRole: 'provider',
            userId: $request->user()->id,
            reason: 'Provider arrived on site and commenced work'
        );

        return $this->successResponse($updated, 'Job started.');
    }

    /**
     * Provider: Complete Job
     */
    public function completeJob(Request $request, string $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);

        $updated = $this->stateMachine->transition(
            booking: $booking,
            targetStatus: 'completed',
            actorRole: 'provider',
            userId: $request->user()->id,
            reason: 'Provider finished job'
        );

        // Financial Posting on Completion
        $commissionAmount = round((float)$booking->agreed_total_price * ((float)$booking->commission_rate / 100), 2);

        if ($booking->payment_mode === 'online_escrow') {
            $this->ledgerService->postOnlineCompletion($booking, (float)$booking->agreed_total_price, $commissionAmount, $request->user()->id);
            $booking->update(['payment_status' => 'released_to_provider']);
        } elseif ($booking->payment_mode === 'cash_on_delivery') {
            // Recognize commission receivable from provider
            $this->ledgerService->postCodCompletion($booking, $commissionAmount, $request->user()->id);
        }

        return $this->successResponse($updated, 'Job successfully completed and finances posted.');
    }

    /**
     * Cancel Booking
     */
    public function cancelBooking(Request $request, string $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);
        $role = $request->user()->hasRole('customer') ? 'customer' : ($request->user()->hasRole('provider') ? 'provider' : 'admin');
        $reason = $request->input('reason', 'User requested cancellation');

        $updated = $this->stateMachine->transition(
            booking: $booking,
            targetStatus: 'cancelled',
            actorRole: $role,
            userId: $request->user()->id,
            reason: $reason
        );

        // Financial accounting for cancellation
        if ($role === 'customer' && $updated->cancellation_fee > 0) {
            // Dispatched travel fee
            $this->ledgerService->postCustomerCancellationWithDispatchFee($booking, (float)$updated->cancellation_fee, $request->user()->id);
        } elseif ($role === 'provider' && $updated->cancellation_fee > 0) {
            // Provider unexcused cancellation penalty
            $this->ledgerService->postProviderPenalty($booking, (float)$updated->cancellation_fee, 'Unexcused post-acceptance cancellation', $request->user()->id);
        }

        return $this->successResponse($updated, 'Booking cancelled.');
    }

    /**
     * Provider: Submit inspection quotation
     */
    public function submitQuotation(Request $request, string $id): JsonResponse
    {
        $provider = $request->user()->providerProfile;
        if (!$provider) {
            return $this->errorResponse('PROFILE_NOT_FOUND', 'Provider profile not found.', null, 404);
        }

        $booking = Booking::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'inspection_notes' => 'nullable|string|max:1000',
            'line_items' => 'required|array|min:1',
            'line_items.*.description' => 'required|string|max:255',
            'line_items.*.item_type' => 'required|in:labor,material,equipment',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.quantity' => 'required|numeric|min:0.1',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid quotation items.', $validator->errors(), 422);
        }

        try {
            $quotation = $this->lifecycleService->submitQuotation(
                booking: $booking,
                provider: $provider,
                lineItems: $request->input('line_items'),
                inspectionNotes: $request->input('inspection_notes')
            );
            return $this->successResponse($quotation->load('items'), 'Quotation submitted successfully.', [], 201);
        } catch (\Exception $e) {
            return $this->errorResponse('QUOTATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    /**
     * Customer: Accept inspection quotation
     */
    public function acceptQuotation(Request $request, string $id, string $quoteId): JsonResponse
    {
        $booking = Booking::findOrFail($id);
        $quotation = Quotation::findOrFail($quoteId);

        $paymentMode = $request->input('payment_mode', $booking->payment_mode ?? 'online_escrow');

        try {
            $confirmed = $this->lifecycleService->acceptQuotation(
                booking: $booking,
                quotation: $quotation,
                paymentMode: $paymentMode
            );
            return $this->successResponse($confirmed, 'Quotation accepted and booking confirmed.');
        } catch (\Exception $e) {
            return $this->errorResponse('QUOTATION_ACCEPTANCE_ERROR', $e->getMessage(), null, 422);
        }
    }

    /**
     * Customer: Reject inspection quotation
     */
    public function rejectQuotation(Request $request, string $id, string $quoteId): JsonResponse
    {
        $booking = Booking::findOrFail($id);
        $quotation = Quotation::where('booking_id', $booking->id)->where('id', $quoteId)->firstOrFail();

        $reason = $request->input('reason', 'Customer rejected quotation.');

        $quotation->update([
            'status' => 'rejected',
            'customer_rejected_reason' => $reason,
        ]);

        return $this->successResponse($quotation, 'Quotation rejected.');
    }

    /**
     * Request rescheduling
     */
    public function requestReschedule(Request $request, string $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'new_start_at' => 'required|date|after:now',
            'new_end_at' => 'required|date|after:new_start_at',
            'reason' => 'required|string|min:5|max:500',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid reschedule parameters.', $validator->errors(), 422);
        }

        $role = $request->user()->hasRole('customer') ? 'customer' : ($request->user()->hasRole('provider') ? 'provider' : 'admin');

        try {
            $updated = $this->lifecycleService->requestReschedule(
                booking: $booking,
                newStart: new \DateTime($request->input('new_start_at')),
                newEnd: new \DateTime($request->input('new_end_at')),
                reason: $request->input('reason'),
                actorRole: $role,
                userId: $request->user()->id
            );
            return $this->successResponse($updated, 'Reschedule request submitted.');
        } catch (\Exception $e) {
            return $this->errorResponse('RESCHEDULE_ERROR', $e->getMessage(), null, 422);
        }
    }

    /**
     * Confirm mutual rescheduling inside same database transaction
     */
    public function confirmReschedule(Request $request, string $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);
        $role = $request->user()->hasRole('customer') ? 'customer' : ($request->user()->hasRole('provider') ? 'provider' : 'admin');

        try {
            $confirmed = $this->lifecycleService->confirmReschedule(
                booking: $booking,
                actorRole: $role,
                userId: $request->user()->id
            );
            return $this->successResponse($confirmed, 'Rescheduled appointment confirmed.');
        } catch (\Exception $e) {
            return $this->errorResponse('RESCHEDULE_CONFIRM_ERROR', $e->getMessage(), null, 422);
        }
    }

    /**
     * Report No-Show (Customer or Provider)
     */
    public function reportNoShow(Request $request, string $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'responsible_party' => 'required|in:customer,provider',
            'reason' => 'required|string|min:5|max:500',
            'evidence' => 'nullable|array',
            'wait_minutes' => 'nullable|integer',
            'calls_logged' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid no-show parameters.', $validator->errors(), 422);
        }

        $responsible = $request->input('responsible_party');
        $role = $request->user()->hasRole('customer') ? 'customer' : ($request->user()->hasRole('provider') ? 'provider' : 'admin');

        // Validation for customer no-show (reported by technician)
        if ($responsible === 'customer' && $role === 'provider') {
            $waitMinutes = (int) $request->input('wait_minutes', 0);
            $callsLogged = (int) $request->input('calls_logged', 0);
            $requiredWait = (int) config('marketplace.no_show.customer_wait_threshold_minutes', 20);
            $requiredCalls = (int) config('marketplace.no_show.minimum_logged_calls', 2);

            if ($waitMinutes < $requiredWait || $callsLogged < $requiredCalls) {
                return $this->errorResponse(
                    'NO_SHOW_POLICY_NOT_MET',
                    "Customer no-show requires at least {$requiredWait} minutes on-site wait time and {$requiredCalls} logged calls.",
                    ['wait_minutes' => $waitMinutes, 'calls_logged' => $callsLogged],
                    422
                );
            }
        }

        try {
            $updated = $this->lifecycleService->recordNoShow(
                booking: $booking,
                responsibleParty: $responsible,
                reason: $request->input('reason'),
                evidence: $request->input('evidence', []),
                actorRole: $role,
                userId: $request->user()->id
            );
            return $this->successResponse($updated, 'No-show recorded and settlement processed.');
        } catch (\Exception $e) {
            return $this->errorResponse('NO_SHOW_ERROR', $e->getMessage(), null, 422);
        }
    }

    /**
     * Customer bookings list
     */
    public function customerBookings(Request $request): JsonResponse
    {
        $customer = $request->user()->customerProfile;
        $bookings = Booking::where('customer_profile_id', $customer->id)
            ->with(['service', 'providerProfile.user', 'address'])
            ->latest('created_at')
            ->paginate(15);

        return $this->successResponse($bookings);
    }

    /**
     * Provider jobs list
     */
    public function providerJobs(Request $request): JsonResponse
    {
        $provider = $request->user()->providerProfile;
        $jobs = Booking::where('provider_profile_id', $provider->id)
            ->with(['service', 'customerProfile.user', 'address'])
            ->latest('created_at')
            ->paginate(15);

        return $this->successResponse($jobs);
    }

    /**
     * Show booking details
     */
    public function showBooking(string $id): JsonResponse
    {
        $booking = Booking::with(['service', 'items', 'quotations.items', 'statusHistory', 'attachments', 'address'])->findOrFail($id);

        return $this->successResponse($booking);
    }
}
