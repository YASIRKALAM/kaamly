import 'customer_profile.dart';
import 'provider_profile.dart';

class User {
  final String id;
  final String firstName;
  final String lastName;
  final String phoneNumber;
  final String? email;
  final String userType;
  final String status;
  final List<String> roles;
  final CustomerProfile? customerProfile;
  final ProviderProfile? providerProfile;

  User({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.phoneNumber,
    this.email,
    required this.userType,
    required this.status,
    required this.roles,
    this.customerProfile,
    this.providerProfile,
  });

  String get fullName => '$firstName $lastName'.trim();

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] ?? '',
      firstName: json['first_name'] ?? '',
      lastName: json['last_name'] ?? '',
      phoneNumber: json['phone_number'] ?? '',
      email: json['email'],
      userType: json['user_type'] ?? 'customer',
      status: json['status'] ?? 'active',
      roles: (json['roles'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [],
      customerProfile: json['customer_profile'] != null
          ? CustomerProfile.fromJson(json['customer_profile'])
          : null,
      providerProfile: json['provider_profile'] != null
          ? ProviderProfile.fromJson(json['provider_profile'])
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'first_name': firstName,
      'last_name': lastName,
      'phone_number': phoneNumber,
      'email': email,
      'user_type': userType,
      'status': status,
      'roles': roles,
      'customer_profile': customerProfile?.toJson(),
      'provider_profile': providerProfile?.toJson(),
    };
  }
}
