class ProviderProfile {
  final String id;
  final String userId;
  final String businessName;
  final String status; // draft, pending_approval, approved, rejected, suspended
  final bool isOnline;
  final bool isAvailable;
  final double ratingAverage;
  final int totalReviews;
  final int completedJobsCount;
  final double acceptanceRate;
  final double? currentLatitude;
  final double? currentLongitude;
  final String? experienceYears;

  ProviderProfile({
    required this.id,
    required this.userId,
    required this.businessName,
    required this.status,
    required this.isOnline,
    required this.isAvailable,
    required this.ratingAverage,
    required this.totalReviews,
    required this.completedJobsCount,
    required this.acceptanceRate,
    this.currentLatitude,
    this.currentLongitude,
    this.experienceYears,
  });

  bool get isApproved => status == 'approved';

  factory ProviderProfile.fromJson(Map<String, dynamic> json) {
    return ProviderProfile(
      id: json['id'] ?? '',
      userId: json['user_id'] ?? '',
      businessName: json['business_name'] ?? '',
      status: json['status'] ?? 'draft',
      isOnline: json['is_online'] ?? false,
      isAvailable: json['is_available'] ?? false,
      ratingAverage: (json['rating_average'] as num?)?.toDouble() ?? 0.0,
      totalReviews: (json['total_reviews'] as num?)?.toInt() ?? 0,
      completedJobsCount: (json['completed_jobs_count'] as num?)?.toInt() ?? 0,
      acceptanceRate: (json['acceptance_rate'] as num?)?.toDouble() ?? 100.0,
      currentLatitude: (json['current_latitude'] as num?)?.toDouble(),
      currentLongitude: (json['current_longitude'] as num?)?.toDouble(),
      experienceYears: json['experience_years']?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'user_id': userId,
      'business_name': businessName,
      'status': status,
      'is_online': isOnline,
      'is_available': isAvailable,
      'rating_average': ratingAverage,
      'total_reviews': totalReviews,
      'completed_jobs_count': completedJobsCount,
      'acceptance_rate': acceptanceRate,
      'current_latitude': currentLatitude,
      'current_longitude': currentLongitude,
      'experience_years': experienceYears,
    };
  }
}
