<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domains\Booking\Models\Booking;
use App\Domains\Review\Models\Dispute;
use App\Domains\Review\Services\ReviewAndDisputeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    protected ReviewAndDisputeService $reviewService;

    public function __construct(ReviewAndDisputeService $reviewService)
    {
        $this->reviewService = $reviewService;
    }

    /**
     * Submit review & rating
     */
    public function submitReview(Request $request, string $bookingId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid review submission.', $validator->errors(), 422);
        }

        $booking = Booking::with(['customerProfile', 'providerProfile'])->findOrFail($bookingId);

        $review = $this->reviewService->submitReview(
            booking: $booking,
            reviewer: $request->user(),
            rating: (int) $request->input('rating'),
            comment: $request->input('comment')
        );

        return $this->successResponse($review, 'Review submitted successfully.', [], 201);
    }

    /**
     * Raise formal booking dispute
     */
    public function raiseDispute(Request $request, string $bookingId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:1000',
            'claim_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid dispute details.', $validator->errors(), 422);
        }

        $booking = Booking::findOrFail($bookingId);

        $dispute = $this->reviewService->raiseDispute(
            booking: $booking,
            raisedBy: $request->user(),
            reason: $request->input('reason'),
            claimAmount: (float) $request->input('claim_amount', 0.0)
        );

        return $this->successResponse($dispute, 'Dispute raised and placed under investigation.', [], 201);
    }

    /**
     * Admin: Arbitrate and resolve dispute
     */
    public function arbitrateDispute(Request $request, string $disputeId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'resolution' => 'required|in:resolved_refund,resolved_payout,resolved_split,rejected',
            'refund_amount' => 'required|numeric|min:0',
            'payout_amount' => 'required|numeric|min:0',
            'notes' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid arbitration parameters.', $validator->errors(), 422);
        }

        $dispute = Dispute::with('booking')->findOrFail($disputeId);

        $resolved = $this->reviewService->arbitrateDispute(
            dispute: $dispute,
            resolution: $request->input('resolution'),
            refundAmount: (float) $request->input('refund_amount'),
            payoutAmount: (float) $request->input('payout_amount'),
            notes: $request->input('notes'),
            arbitrator: $request->user()
        );

        return $this->successResponse($resolved, 'Dispute resolved and settled.');
    }
}
