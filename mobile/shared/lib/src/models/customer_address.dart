class CustomerAddress {
  final String id;
  final String customerProfileId;
  final String addressTitle; // e.g. "Home", "Office"
  final String streetAddress;
  final String city;
  final String? area;
  final double latitude;
  final double longitude;
  final bool isDefault;

  CustomerAddress({
    required this.id,
    required this.customerProfileId,
    required this.addressTitle,
    required this.streetAddress,
    required this.city,
    this.area,
    required this.latitude,
    required this.longitude,
    required this.isDefault,
  });

  String get fullAddress => area != null && area!.isNotEmpty
      ? '$streetAddress, $area, $city'
      : '$streetAddress, $city';

  factory CustomerAddress.fromJson(Map<String, dynamic> json) {
    return CustomerAddress(
      id: json['id'] ?? '',
      customerProfileId: json['customer_profile_id'] ?? '',
      addressTitle: json['address_title'] ?? 'Home',
      streetAddress: json['street_address'] ?? '',
      city: json['city'] ?? '',
      area: json['area'],
      latitude: (json['latitude'] as num?)?.toDouble() ?? 0.0,
      longitude: (json['longitude'] as num?)?.toDouble() ?? 0.0,
      isDefault: json['is_default'] ?? false,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'customer_profile_id': customerProfileId,
      'address_title': addressTitle,
      'street_address': streetAddress,
      'city': city,
      'area': area,
      'latitude': latitude,
      'longitude': longitude,
      'is_default': isDefault,
    };
  }
}
