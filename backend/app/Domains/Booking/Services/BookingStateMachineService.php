<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingStatusHistory;
use App\Domains\Booking\Exceptions\BookingStateTransitionException;
use Illuminate\Support\Facades\DB;

class BookingStateMachineService
{
    /**
     * Map of permitted transitions: [CurrentStatus => [TargetStatus => [AllowedRoles]]]
     */
    protected const PERMITTED_TRANSITIONS = [
        'pending' => [
            'broadcasted' => ['system', 'admin'],
            'accepted' => ['provider', 'admin'],
            'cancelled' => ['customer', 'system', 'admin'],
        ],
        'broadcasted' => [
            'accepted' => ['provider', 'admin'],
            'cancelled' => ['customer', 'system', 'admin'],
        ],
        'accepted' => [
            'confirmed' => ['customer', 'system', 'admin'],
            'quotation_submitted' => ['provider'],
            'cancelled' => ['customer', 'provider', 'admin'],
            'rescheduled' => ['customer', 'provider', 'admin'],
        ],
        'quotation_submitted' => [
            'awaiting_customer_confirmation' => ['system', 'provider'],
            'cancelled' => ['customer', 'admin'],
        ],
        'awaiting_customer_confirmation' => [
            'confirmed' => ['customer', 'admin'],
            'cancelled' => ['customer', 'admin'],
            'quotation_submitted' => ['provider'], // provider revisions
        ],
        'confirmed' => [
            'provider_on_the_way' => ['provider'],
            'cancelled' => ['customer', 'provider', 'admin'],
            'rescheduled' => ['customer', 'provider', 'admin'],
            'no_show' => ['customer', 'provider', 'admin', 'system'],
        ],
        'provider_on_the_way' => [
            'in_progress' => ['provider'],
            'cancelled' => ['customer', 'provider', 'admin'],
            'no_show' => ['customer', 'provider', 'admin', 'system'],
        ],
        'in_progress' => [
            'completed' => ['provider', 'admin'],
            'disputed' => ['customer', 'admin'],
        ],
        'completed' => [
            'disputed' => ['customer', 'admin'], // within 72-hour dispute window
        ],
        'rescheduled' => [
            'confirmed' => ['customer', 'provider', 'admin'], // mutual confirmation
            'cancelled' => ['customer', 'provider', 'admin'],
        ],
        'disputed' => [
            'completed' => ['admin'], // dispute resolved in favor of completion
            'cancelled' => ['admin'], // dispute resolved with full cancellation / refund
        ],
        'cancelled' => [], // Terminal state
        'no_show' => [],   // Terminal state
    ];

    /**
     * Transition a booking safely within a database transaction.
     */
    public function transition(
        Booking $booking,
        string $targetStatus,
        string $actorRole,
        ?string $userId = null,
        ?string $reason = null,
        array $metadata = []
    ): Booking {
        $currentStatus = $booking->status;

        // 1. Validate transition
        if (!$this->canTransition($currentStatus, $targetStatus, $actorRole)) {
            throw new BookingStateTransitionException($currentStatus, $targetStatus, $actorRole);
        }

        return DB::transaction(function () use ($booking, $currentStatus, $targetStatus, $actorRole, $userId, $reason, $metadata) {
            // Lock booking row
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            // Set timestamps & lifecycle fields
            $updates = ['status' => $targetStatus];

            if ($targetStatus === 'provider_on_the_way') {
                $updates['actual_started_at'] = null; // En route
            } elseif ($targetStatus === 'in_progress') {
                $updates['actual_started_at'] = now();
            } elseif ($targetStatus === 'completed') {
                $updates['actual_completed_at'] = now();
            } elseif ($targetStatus === 'cancelled') {
                $updates['cancelled_at'] = now();
                $updates['cancelled_by'] = $userId;
                $updates['cancellation_reason'] = $reason;

                // Cancellation Fee Logic
                if ($actorRole === 'customer' && $currentStatus === 'provider_on_the_way') {
                    // Customer cancels after technician is dispatched: travel compensation fee
                    $updates['cancellation_fee'] = (float) config('marketplace.cancellation_fees.customer_post_dispatch', 500.00);
                } elseif ($actorRole === 'provider' && in_array($currentStatus, ['accepted', 'confirmed'])) {
                    // Provider unexcused cancellation post-acceptance: platform penalty
                    $updates['cancellation_fee'] = (float) config('marketplace.cancellation_fees.provider_unexcused', 300.00);
                }
            } elseif ($targetStatus === 'no_show') {
                $responsible = $metadata['responsible_party'] ?? $metadata['no_show_by'] ?? ($actorRole === 'provider' ? 'customer' : 'provider');
                $updates['no_show_by'] = $responsible;
                $updates['no_show_responsible_party'] = $responsible;
                $updates['no_show_reason'] = $reason ?? ($metadata['reason'] ?? null);
                $updates['no_show_evidence'] = $metadata['evidence'] ?? null;
                $updates['no_show_recorded_by'] = $userId;
                $updates['no_show_recorded_at'] = now();
                $updates['cancellation_fee'] = $responsible === 'customer' 
                    ? (float) config('marketplace.no_show.customer_travel_compensation_fee', 500.00)
                    : (float) config('marketplace.no_show.provider_penalty_fee', 500.00);
            }

            $lockedBooking->update($updates);

            // Record status history audit
            BookingStatusHistory::create([
                'booking_id' => $lockedBooking->id,
                'previous_status' => $currentStatus,
                'new_status' => $targetStatus,
                'reason' => $reason,
                'metadata' => array_merge($metadata, [
                    'actor_role' => $actorRole,
                    'actor_user_id' => $userId,
                ]),
                'changed_by' => $userId,
                'created_at' => now(),
            ]);

            return $lockedBooking->fresh();
        });
    }

    /**
     * Check if a transition is valid.
     */
    public function canTransition(string $currentStatus, string $targetStatus, string $actorRole): bool
    {
        if (!isset(self::PERMITTED_TRANSITIONS[$currentStatus])) {
            return false;
        }

        $targets = self::PERMITTED_TRANSITIONS[$currentStatus];
        if (!isset($targets[$targetStatus])) {
            return false;
        }

        return in_array($actorRole, $targets[$targetStatus], true) || $actorRole === 'admin';
    }
}
