<?php

namespace App\Domains\Review\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\BookingStateMachineService;
use App\Domains\Identity\Models\User;
use App\Domains\Provider\Models\ProviderProfile;
use App\Domains\Review\Models\Review;
use App\Domains\Review\Models\Dispute;
use App\Domains\Financial\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReviewAndDisputeService
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
     * Submit review and update provider quality rating metrics
     */
    public function submitReview(
        Booking $booking,
        User $reviewer,
        int $rating,
        ?string $comment = null
    ): Review {
        if ($booking->status !== 'completed') {
            throw new InvalidArgumentException("Reviews can only be submitted for completed bookings.");
        }

        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException("Rating score must be between 1 and 5.");
        }

        $isCustomer = $booking->customerProfile && $booking->customerProfile->user_id === $reviewer->id;
        $isProvider = $booking->providerProfile && $booking->providerProfile->user_id === $reviewer->id;

        if (!$isCustomer && !$isProvider) {
            throw new InvalidArgumentException("Only participants of the booking may submit a review.");
        }

        $revieweeId = $isCustomer 
            ? $booking->providerProfile->user_id 
            : $booking->customerProfile->user_id;

        return DB::transaction(function () use ($booking, $reviewer, $revieweeId, $rating, $comment, $isCustomer) {
            $review = Review::create([
                'booking_id' => $booking->id,
                'reviewer_user_id' => $reviewer->id,
                'reviewee_user_id' => $revieweeId,
                'rating' => $rating,
                'comment' => $comment,
                'is_public' => true,
            ]);

            // If customer reviewed provider, recalculate provider's rolling average rating
            if ($isCustomer && $booking->provider_profile_id) {
                $provider = ProviderProfile::findOrFail($booking->provider_profile_id);
                
                $stats = DB::table('reviews')
                    ->where('reviewee_user_id', $provider->user_id)
                    ->selectRaw('COUNT(*) as total, AVG(rating) as avg_rating')
                    ->first();

                $provider->update([
                    'total_reviews' => (int) $stats->total,
                    'rating_average' => round((float) $stats->avg_rating, 2),
                ]);
            }

            return $review;
        });
    }

    /**
     * Raise a formal dispute on a booking
     */
    public function raiseDispute(
        Booking $booking,
        User $raisedBy,
        string $reason,
        float $claimAmount = 0.00
    ): Dispute {
        if (!in_array($booking->status, ['in_progress', 'completed'])) {
            throw new InvalidArgumentException("Disputes can only be raised for jobs in-progress or completed.");
        }

        return DB::transaction(function () use ($booking, $raisedBy, $reason, $claimAmount) {
            $dispute = Dispute::create([
                'booking_id' => $booking->id,
                'raised_by_user_id' => $raisedBy->id,
                'dispute_reason' => $reason,
                'dispute_status' => 'opened',
                'customer_claim_amount' => $claimAmount,
            ]);

            $this->stateMachine->transition(
                booking: $booking,
                targetStatus: 'disputed',
                actorRole: $raisedBy->hasRole('customer') ? 'customer' : 'admin',
                userId: $raisedBy->id,
                reason: "Dispute opened: {$reason}"
            );

            return $dispute;
        });
    }

    /**
     * Admin: Arbitrate and resolve dispute
     */
    public function arbitrateDispute(
        Dispute $dispute,
        string $resolution, // resolved_refund, resolved_payout, resolved_split, rejected
        float $refundAmount,
        float $payoutAmount,
        string $notes,
        User $arbitrator
    ): Dispute {
        return DB::transaction(function () use ($dispute, $resolution, $refundAmount, $payoutAmount, $notes, $arbitrator) {
            $booking = $dispute->booking;
            $agreedTotal = (float) $booking->agreed_total_price;

            if (round($refundAmount + $payoutAmount, 2) > $agreedTotal) {
                throw new InvalidArgumentException("Combined refund (PKR {$refundAmount}) and payout (PKR {$payoutAmount}) cannot exceed booking total (PKR {$agreedTotal}).");
            }

            $dispute->update([
                'dispute_status' => $resolution,
                'resolved_refund_amount' => $refundAmount,
                'resolved_payout_amount' => $payoutAmount,
                'arbitrator_user_id' => $arbitrator->id,
                'arbitration_notes' => $notes,
                'resolved_at' => now(),
            ]);

            // Financial settlement of disputed funds
            if ($booking->payment_mode === 'online_escrow') {
                if ($refundAmount > 0) {
                    // Refund portion: Debit Escrow 2010 -> Credit Platform Bank 1010
                    $this->ledgerService->postTransaction(
                        idempotencyKey: "DISPUTE-REFUND-{$dispute->id}",
                        referenceType: 'dispute_resolution',
                        referenceId: $dispute->id,
                        entries: [
                            ['account_code' => '2010', 'direction' => 'debit', 'amount' => $refundAmount, 'description' => "Dispute refund to customer for Booking #{$booking->booking_number}"],
                            ['account_code' => '1010', 'direction' => 'credit', 'amount' => $refundAmount, 'description' => "Bank gateway refund disbursement"],
                        ],
                        description: "Arbitrated refund for Booking #{$booking->booking_number}",
                        userId: $arbitrator->id
                    );
                }

                if ($payoutAmount > 0) {
                    // Payout portion: Debit Escrow 2010 -> Credit Provider Payable 2020
                    $this->ledgerService->postTransaction(
                        idempotencyKey: "DISPUTE-PAYOUT-{$dispute->id}",
                        referenceType: 'dispute_resolution',
                        referenceId: $dispute->id,
                        entries: [
                            ['account_code' => '2010', 'direction' => 'debit', 'amount' => $payoutAmount, 'description' => "Dispute released payout for Booking #{$booking->booking_number}"],
                            ['account_code' => '2020', 'direction' => 'credit', 'amount' => $payoutAmount, 'description' => "Provider payable from resolved dispute"],
                        ],
                        description: "Arbitrated provider earnings for Booking #{$booking->booking_number}",
                        userId: $arbitrator->id
                    );
                }
            }

            return $dispute->fresh();
        });
    }
}
