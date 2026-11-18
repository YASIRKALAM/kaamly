import 'customer_address.dart';

class CustomerProfile {
  final String id;
  final String userId;
  final String? profilePhotoUrl;
  final String preferredLanguage;
  final List<CustomerAddress> addresses;

  CustomerProfile({
    required this.id,
    required this.userId,
    this.profilePhotoUrl,
    required this.preferredLanguage,
    this.addresses = const [],
  });

  factory CustomerProfile.fromJson(Map<String, dynamic> json) {
    return CustomerProfile(
      id: json['id'] ?? '',
      userId: json['user_id'] ?? '',
      profilePhotoUrl: json['profile_photo_url'],
      preferredLanguage: json['preferred_language'] ?? 'en',
      addresses: (json['addresses'] as List<dynamic>?)
              ?.map((e) => CustomerAddress.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'user_id': userId,
      'profile_photo_url': profilePhotoUrl,
      'preferred_language': preferredLanguage,
      'addresses': addresses.map((e) => e.toJson()).toList(),
    };
  }
}
