class Review {
  final String id;
  final String bookingId;
  final String reviewerUserId;
  final String revieweeUserId;
  final int rating;
  final String? comment;
  final DateTime createdAt;

  Review({
    required this.id,
    required this.bookingId,
    required this.reviewerUserId,
    required this.revieweeUserId,
    required this.rating,
    this.comment,
    required this.createdAt,
  });

  factory Review.fromJson(Map<String, dynamic> json) {
    return Review(
      id: json['id'] ?? '',
      bookingId: json['booking_id'] ?? '',
      reviewerUserId: json['reviewer_user_id'] ?? '',
      revieweeUserId: json['reviewee_user_id'] ?? '',
      rating: (json['rating'] as num?)?.toInt() ?? 5,
      comment: json['comment'],
      createdAt: DateTime.parse(json['created_at']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'booking_id': bookingId,
      'reviewer_user_id': reviewerUserId,
      'reviewee_user_id': revieweeUserId,
      'rating': rating,
      'comment': comment,
      'created_at': createdAt.toIso8601String(),
    };
  }
}

class Dispute {
  final String id;
  final String bookingId;
  final String raisedByUserId;
  final String disputeReason;
  final String disputeStatus; // opened, resolved_refund, resolved_payout, resolved_split, rejected
  final double customerClaimAmount;
  final double? resolvedRefundAmount;
  final double? resolvedPayoutAmount;
  final String? arbitrationNotes;
  final DateTime? resolvedAt;

  Dispute({
    required this.id,
    required this.bookingId,
    required this.raisedByUserId,
    required this.disputeReason,
    required this.disputeStatus,
    required this.customerClaimAmount,
    this.resolvedRefundAmount,
    this.resolvedPayoutAmount,
    this.arbitrationNotes,
    this.resolvedAt,
  });

  factory Dispute.fromJson(Map<String, dynamic> json) {
    return Dispute(
      id: json['id'] ?? '',
      bookingId: json['booking_id'] ?? '',
      raisedByUserId: json['raised_by_user_id'] ?? '',
      disputeReason: json['dispute_reason'] ?? '',
      disputeStatus: json['dispute_status'] ?? 'opened',
      customerClaimAmount: (json['customer_claim_amount'] as num?)?.toDouble() ?? 0.0,
      resolvedRefundAmount: (json['resolved_refund_amount'] as num?)?.toDouble(),
      resolvedPayoutAmount: (json['resolved_payout_amount'] as num?)?.toDouble(),
      arbitrationNotes: json['arbitration_notes'],
      resolvedAt: json['resolved_at'] != null ? DateTime.parse(json['resolved_at']) : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'booking_id': bookingId,
      'raised_by_user_id': raisedByUserId,
      'dispute_reason': disputeReason,
      'dispute_status': disputeStatus,
      'customer_claim_amount': customerClaimAmount,
      'resolved_refund_amount': resolvedRefundAmount,
      'resolved_payout_amount': resolvedPayoutAmount,
      'arbitration_notes': arbitrationNotes,
      'resolved_at': resolvedAt?.toIso8601String(),
    };
  }
}
