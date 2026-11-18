<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingItem;
use App\Domains\Booking\Models\Quotation;
use App\Domains\Booking\Models\QuotationItem;
use App\Domains\Customer\Models\CustomerProfile;
use App\Domains\Customer\Models\CustomerAddress;
use App\Domains\Provider\Models\ProviderProfile;
use App\Domains\Service\Models\Service;
use App\Domains\Financial\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use DateTime;

class BookingLifecycleService
{
    protected BookingStateMachineService $stateMachine;
    protected LedgerService $ledgerService;

    public function __construct(
        BookingStateMachineService $stateMachine,
        LedgerService $ledgerService
    ) {
        $this->stateMachine = $stateMachine;
        $this->ledgerService = $ledgerService;
    }

    /**
     * Create a standard booking from a catalog service
     */
    public function createBooking(
        CustomerProfile $customer,
        CustomerAddress $address,
        Service $service,
        DateTime $scheduledStart,
        string $paymentMode = 'online_escrow',
        ?ProviderProfile $provider = null,
        ?string $bookingRequestId = null
    ): Booking {
        // Enforce Address Ownership
        if ($address->customer_profile_id !== $customer->id) {
            throw new InvalidArgumentException("Customer does not own the provided address.");
        }

        $durationMinutes = $service->estimated_duration_minutes ?? 60;
        $scheduledEnd = (clone $scheduledStart)->modify("+{$durationMinutes} minutes");

        return DB::transaction(function () use ($customer, $address, $service, $scheduledStart, $scheduledEnd, $paymentMode, $provider, $bookingRequestId) {
            $bookingNumber = 'BK-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            $basePrice = (float) $service->base_price;
            $commissionRate = 10.00; // 10% platform commission
            $platformFee = round($basePrice * ($commissionRate / 100.0), 2);

            $booking = Booking::create([
                'booking_number' => $bookingNumber,
                'customer_profile_id' => $customer->id,
                'provider_profile_id' => $provider ? $provider->id : null,
                'booking_request_id' => $bookingRequestId,
                'address_id' => $address->id,
                'service_id' => $service->id,
                'status' => 'pending',
                'payment_mode' => $paymentMode,
                'payment_status' => 'pending',
                'scheduled_start_at' => $scheduledStart,
                'scheduled_end_at' => $scheduledEnd,
                'base_price' => $basePrice,
                'agreed_total_price' => $basePrice,
                'commission_rate' => $commissionRate,
                'platform_fee' => $platformFee,
            ]);

            BookingItem::create([
                'booking_id' => $booking->id,
                'service_id' => $service->id,
                'description' => $service->name,
                'unit_price' => $basePrice,
                'quantity' => 1,
                'total_price' => $basePrice,
            ]);

            return $booking;
        });
    }

    /**
     * Submit an inspection quotation by technician
     */
    public function submitQuotation(
        Booking $booking,
        ProviderProfile $provider,
        array $lineItems,
        ?string $inspectionNotes = null
    ): Quotation {
        if ($booking->provider_profile_id !== $provider->id) {
            throw new InvalidArgumentException("Technician is not assigned to this booking.");
        }

        return DB::transaction(function () use ($booking, $provider, $lineItems, $inspectionNotes) {
            $laborTotal = 0.00;
            $materialTotal = 0.00;

            foreach ($lineItems as $item) {
                $itemTotal = round((float)$item['unit_price'] * (float)$item['quantity'], 2);
                if (($item['item_type'] ?? 'labor') === 'material') {
                    $materialTotal += $itemTotal;
                } else {
                    $laborTotal += $itemTotal;
                }
            }

            $totalQuote = round($laborTotal + $materialTotal, 2);

            // Supersede any existing pending/submitted quotations for this booking
            $latestVersion = Quotation::where('booking_id', $booking->id)->max('version') ?? 0;
            Quotation::where('booking_id', $booking->id)
                ->where('status', 'submitted')
                ->update(['status' => 'superseded']);

            $expiryHours = (int) config('marketplace.quotation.expiry_hours', 24);

            $quotation = Quotation::create([
                'booking_id' => $booking->id,
                'provider_profile_id' => $provider->id,
                'version' => $latestVersion + 1,
                'inspection_notes' => $inspectionNotes,
                'labor_cost' => $laborTotal,
                'material_cost' => $materialTotal,
                'total_quote_amount' => $totalQuote,
                'status' => 'submitted',
                'expires_at' => now()->addHours($expiryHours),
            ]);

            foreach ($lineItems as $item) {
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'description' => $item['description'],
                    'item_type' => $item['item_type'] ?? 'labor',
                    'unit_price' => (float) $item['unit_price'],
                    'quantity' => (float) $item['quantity'],
                    'total_price' => round((float)$item['unit_price'] * (float)$item['quantity'], 2),
                ]);
            }

            // Transition booking state
            $this->stateMachine->transition(
                booking: $booking,
                targetStatus: 'quotation_submitted',
                actorRole: 'provider',
                userId: $provider->user_id,
                reason: 'Inspection quote submitted: PKR ' . $totalQuote
            );

            $this->stateMachine->transition(
                booking: $booking,
                targetStatus: 'awaiting_customer_confirmation',
                actorRole: 'system',
                reason: 'Quotation sent to customer for review'
            );

            return $quotation;
        });
    }

    /**
     * Customer accepts inspection quotation and locks the agreed price
     */
    public function acceptQuotation(Booking $booking, Quotation $quotation, string $paymentMode = 'online_escrow'): Booking
    {
        if ($quotation->booking_id !== $booking->id) {
            throw new InvalidArgumentException("Quotation does not belong to this booking.");
        }

        if ($quotation->status === 'superseded') {
            throw new InvalidArgumentException("This quotation has been superseded by a newer version.");
        }

        if ($quotation->status !== 'submitted') {
            throw new InvalidArgumentException("Quotation is not in a submittable state (current: {$quotation->status}).");
        }

        if (now()->greaterThanOrEqualTo($quotation->expires_at)) {
            $quotation->update(['status' => 'expired']);
            throw new InvalidArgumentException("Quotation has expired on {$quotation->expires_at->toIso8601String()}.");
        }

        return DB::transaction(function () use ($booking, $quotation, $paymentMode) {
            $quotation->update(['status' => 'accepted']);

            // Reject or supersede any other quotations for this booking
            Quotation::where('booking_id', $booking->id)
                ->where('id', '!=', $quotation->id)
                ->whereIn('status', ['submitted', 'superseded'])
                ->update(['status' => 'rejected']);

            $newTotalPrice = (float) $quotation->total_quote_amount;
            $platformFee = round($newTotalPrice * ((float)$booking->commission_rate / 100.0), 2);

            // Snapshot agreed price permanently
            $booking->update([
                'payment_mode' => $paymentMode,
                'base_price' => (float)$quotation->labor_cost,
                'additional_charges' => (float)$quotation->material_cost,
                'agreed_total_price' => $newTotalPrice,
                'platform_fee' => $platformFee,
            ]);

            // Transition to confirmed
            $confirmed = $this->stateMachine->transition(
                booking: $booking,
                targetStatus: 'confirmed',
                actorRole: 'customer',
                userId: $booking->customerProfile->user_id,
                reason: 'Customer accepted quotation and locked price.'
            );

            // If Online Escrow, capture holding
            if ($paymentMode === 'online_escrow') {
                $this->ledgerService->postOnlineEscrowHold(
                    booking: $confirmed,
                    amount: $newTotalPrice,
                    paymentRef: 'QUOTATION_CONFIRM_' . Str::random(8),
                    userId: $booking->customerProfile->user_id
                );
                $confirmed->update(['payment_status' => 'escrow_locked']);
            }

            return $confirmed;
        });
    }

    /**
     * Request booking reschedule
     */
    public function requestReschedule(
        Booking $booking,
        DateTime $newStart,
        DateTime $newEnd,
        string $reason,
        string $actorRole,
        string $userId
    ): Booking {
        return DB::transaction(function () use ($booking, $newStart, $newEnd, $reason, $actorRole, $userId) {
            $locked = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'rescheduled_start_at' => $newStart,
                'rescheduled_end_at' => $newEnd,
                'reschedule_reason' => $reason,
                'reschedule_requested_by' => $userId,
            ]);

            return $this->stateMachine->transition(
                booking: $locked,
                targetStatus: 'rescheduled',
                actorRole: $actorRole,
                userId: $userId,
                reason: "Reschedule requested: {$reason}"
            );
        });
    }

    /**
     * Confirm mutual reschedule inside same database transaction
     */
    public function confirmReschedule(
        Booking $booking,
        string $actorRole,
        string $userId
    ): Booking {
        return DB::transaction(function () use ($booking, $actorRole, $userId) {
            $locked = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            if (!$locked->rescheduled_start_at || !$locked->rescheduled_end_at) {
                throw new InvalidArgumentException("No proposed rescheduled time found on booking.");
            }

            // Apply new schedule to primary scheduled fields
            // (automatically updates scheduled_range generated column and validates GiST exclusion)
            $newStart = $locked->rescheduled_start_at;
            $newEnd = $locked->rescheduled_end_at;

            $locked->update([
                'scheduled_start_at' => $newStart,
                'scheduled_end_at' => $newEnd,
                'rescheduled_start_at' => null,
                'rescheduled_end_at' => null,
            ]);

            // Transition to confirmed
            return $this->stateMachine->transition(
                booking: $locked,
                targetStatus: 'confirmed',
                actorRole: $actorRole,
                userId: $userId,
                reason: 'Rescheduled appointment confirmed mutually.'
            );
        });
    }

    /**
     * Record no-show with distinct financial treatment
     */
    public function recordNoShow(
        Booking $booking,
        string $responsibleParty, // 'customer' or 'provider'
        string $reason,
        array $evidence,
        string $actorRole,
        string $userId
    ): Booking {
        return DB::transaction(function () use ($booking, $responsibleParty, $reason, $evidence, $actorRole, $userId) {
            $locked = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            $updated = $this->stateMachine->transition(
                booking: $locked,
                targetStatus: 'no_show',
                actorRole: $actorRole,
                userId: $userId,
                reason: $reason,
                metadata: [
                    'no_show_by' => $responsibleParty,
                    'responsible_party' => $responsibleParty,
                    'reason' => $reason,
                    'evidence' => $evidence,
                ]
            );

            // Financial Treatment
            $total = (float) $updated->agreed_total_price;
            $travelFee = (float) config('marketplace.no_show.customer_travel_compensation_fee', 500.00);
            $providerPenalty = (float) config('marketplace.no_show.provider_penalty_fee', 500.00);

            if ($responsibleParty === 'customer') {
                // Customer no-show: If online escrow, compensate provider travel fee and refund remainder
                if ($updated->payment_mode === 'online_escrow') {
                    $this->ledgerService->postCustomerNoShowSettlement($updated, $total, $travelFee, $userId);
                    $updated->update(['payment_status' => 'released_to_provider']);
                }
            } else {
                // Provider no-show: Customer gets 100% refund; Provider fined penalty debt
                if ($updated->payment_mode === 'online_escrow') {
                    $this->ledgerService->postProviderNoShowCustomerRefund($updated, $total, $userId);
                    $updated->update(['payment_status' => 'refunded']);
                }
                $this->ledgerService->postProviderPenalty($updated, $providerPenalty, 'Provider No-Show Missed Appointment', $userId);
            }

            return $updated;
        });
    }
    }

    /**
     * Complete booking and trigger double-entry financial posting
     */
    public function completeBooking(Booking $booking, string $providerUserId): Booking
    {
        return DB::transaction(function () use ($booking, $providerUserId) {
            $completed = $this->stateMachine->transition(
                booking: $booking,
                targetStatus: 'completed',
                actorRole: 'provider',
                userId: $providerUserId,
                reason: 'Technician reported job completion.'
            );

            $total = (float) $completed->agreed_total_price;
            $commission = round($total * ((float)$completed->commission_rate / 100.0), 2);

            if ($completed->payment_mode === 'online_escrow') {
                // Online Escrow Release
                $this->ledgerService->postOnlineCompletion($completed, $total, $commission, $providerUserId);
                $completed->update(['payment_status' => 'released_to_provider']);
            } elseif ($completed->payment_mode === 'cash_on_delivery') {
                // COD Commission Recognized
                $this->ledgerService->postCodCompletion($completed, $commission, $providerUserId);
            }

            // Update Provider metrics
            if ($completed->provider_profile_id) {
                ProviderProfile::where('id', $completed->provider_profile_id)->increment('completed_jobs_count');
            }

            return $completed;
        });
    }
}
