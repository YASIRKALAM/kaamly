import 'service.dart';
import 'customer_address.dart';
import 'provider_profile.dart';
import 'user.dart';
import 'quotation.dart';

class BookingItem {
  final String id;
  final String bookingId;
  final String? serviceId;
  final String description;
  final double unitPrice;
  final int quantity;
  final double totalPrice;

  BookingItem({
    required this.id,
    required this.bookingId,
    this.serviceId,
    required this.description,
    required this.unitPrice,
    required this.quantity,
    required this.totalPrice,
  });

  factory BookingItem.fromJson(Map<String, dynamic> json) {
    return BookingItem(
      id: json['id'] ?? '',
      bookingId: json['booking_id'] ?? '',
      serviceId: json['service_id'],
      description: json['description'] ?? '',
      unitPrice: (json['unit_price'] as num?)?.toDouble() ?? 0.0,
      quantity: (json['quantity'] as num?)?.toInt() ?? 1,
      totalPrice: (json['total_price'] as num?)?.toDouble() ?? 0.0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'booking_id': bookingId,
      'service_id': serviceId,
      'description': description,
      'unit_price': unitPrice,
      'quantity': quantity,
      'total_price': totalPrice,
    };
  }
}

class Booking {
  final String id;
  final String bookingNumber;
  final String customerProfileId;
  final String? providerProfileId;
  final String addressId;
  final String serviceId;
  final String status;
  final String paymentMode;
  final String paymentStatus;
  final DateTime scheduledStartAt;
  final DateTime scheduledEndAt;
  final DateTime? actualStartedAt;
  final DateTime? actualCompletedAt;
  final double basePrice;
  final double additionalCharges;
  final double agreedTotalPrice;
  final double cancellationFee;
  final String? cancellationReason;
  final String? noShowBy;
  final String? noShowReason;
  final DateTime? rescheduledStartAt;
  final DateTime? rescheduledEndAt;
  final String? rescheduleReason;
  final Service? service;
  final CustomerAddress? address;
  final ProviderProfile? providerProfile;
  final User? providerUser;
  final List<BookingItem> items;
  final List<Quotation> quotations;

  Booking({
    required this.id,
    required this.bookingNumber,
    required this.customerProfileId,
    this.providerProfileId,
    required this.addressId,
    required this.serviceId,
    required this.status,
    required this.paymentMode,
    required this.paymentStatus,
    required this.scheduledStartAt,
    required this.scheduledEndAt,
    this.actualStartedAt,
    this.actualCompletedAt,
    required this.basePrice,
    required this.additionalCharges,
    required this.agreedTotalPrice,
    required this.cancellationFee,
    this.cancellationReason,
    this.noShowBy,
    this.noShowReason,
    this.rescheduledStartAt,
    this.rescheduledEndAt,
    this.rescheduleReason,
    this.service,
    this.address,
    this.providerProfile,
    this.providerUser,
    this.items = const [],
    this.quotations = const [],
  });

  bool get isInspectionWorkflow =>
      status == 'quotation_submitted' || status == 'awaiting_customer_confirmation';

  bool get isActive => [
        'accepted',
        'quotation_submitted',
        'awaiting_customer_confirmation',
        'confirmed',
        'provider_on_the_way',
        'in_progress'
      ].contains(status);

  bool get isTerminal => ['completed', 'cancelled', 'no_show'].contains(status);

  factory Booking.fromJson(Map<String, dynamic> json) {
    return Booking(
      id: json['id'] ?? '',
      bookingNumber: json['booking_number'] ?? '',
      customerProfileId: json['customer_profile_id'] ?? '',
      providerProfileId: json['provider_profile_id'],
      addressId: json['address_id'] ?? '',
      serviceId: json['service_id'] ?? '',
      status: json['status'] ?? 'pending',
      paymentMode: json['payment_mode'] ?? 'online_escrow',
      paymentStatus: json['payment_status'] ?? 'pending',
      scheduledStartAt: DateTime.parse(json['scheduled_start_at']),
      scheduledEndAt: DateTime.parse(json['scheduled_end_at']),
      actualStartedAt: json['actual_started_at'] != null ? DateTime.parse(json['actual_started_at']) : null,
      actualCompletedAt: json['actual_completed_at'] != null ? DateTime.parse(json['actual_completed_at']) : null,
      basePrice: (json['base_price'] as num?)?.toDouble() ?? 0.0,
      additionalCharges: (json['additional_charges'] as num?)?.toDouble() ?? 0.0,
      agreedTotalPrice: (json['agreed_total_price'] as num?)?.toDouble() ?? 0.0,
      cancellationFee: (json['cancellation_fee'] as num?)?.toDouble() ?? 0.0,
      cancellationReason: json['cancellation_reason'],
      noShowBy: json['no_show_by'] ?? json['no_show_responsible_party'],
      noShowReason: json['no_show_reason'],
      rescheduledStartAt: json['rescheduled_start_at'] != null ? DateTime.parse(json['rescheduled_start_at']) : null,
      rescheduledEndAt: json['rescheduled_end_at'] != null ? DateTime.parse(json['rescheduled_end_at']) : null,
      rescheduleReason: json['reschedule_reason'],
      service: json['service'] != null ? Service.fromJson(json['service']) : null,
      address: json['address'] != null ? CustomerAddress.fromJson(json['address']) : null,
      providerProfile: json['provider_profile'] != null ? ProviderProfile.fromJson(json['provider_profile']) : null,
      providerUser: json['provider_profile']?['user'] != null ? User.fromJson(json['provider_profile']['user']) : null,
      items: (json['items'] as List<dynamic>?)?.map((e) => BookingItem.fromJson(e)).toList() ?? [],
      quotations: (json['quotations'] as List<dynamic>?)?.map((e) => Quotation.fromJson(e)).toList() ?? [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'booking_number': bookingNumber,
      'customer_profile_id': customerProfileId,
      'provider_profile_id': providerProfileId,
      'address_id': addressId,
      'service_id': serviceId,
      'status': status,
      'payment_mode': paymentMode,
      'payment_status': paymentStatus,
      'scheduled_start_at': scheduledStartAt.toIso8601String(),
      'scheduled_end_at': scheduledEndAt.toIso8601String(),
      'actual_started_at': actualStartedAt?.toIso8601String(),
      'actual_completed_at': actualCompletedAt?.toIso8601String(),
      'base_price': basePrice,
      'additional_charges': additionalCharges,
      'agreed_total_price': agreedTotalPrice,
      'cancellation_fee': cancellationFee,
      'cancellation_reason': cancellationReason,
      'no_show_by': noShowBy,
      'no_show_reason': noShowReason,
      'rescheduled_start_at': rescheduledStartAt?.toIso8601String(),
      'rescheduled_end_at': rescheduledEndAt?.toIso8601String(),
      'reschedule_reason': rescheduleReason,
      'service': service?.toJson(),
      'address': address?.toJson(),
      'provider_profile': providerProfile?.toJson(),
      'items': items.map((e) => e.toJson()).toList(),
      'quotations': quotations.map((e) => e.toJson()).toList(),
    };
  }
}
