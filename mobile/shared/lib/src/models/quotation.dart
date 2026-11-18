class QuotationItem {
  final String id;
  final String quotationId;
  final String description;
  final String itemType; // labor, material, equipment
  final double unitPrice;
  final double quantity;
  final double totalPrice;

  QuotationItem({
    required this.id,
    required this.quotationId,
    required this.description,
    required this.itemType,
    required this.unitPrice,
    required this.quantity,
    required this.totalPrice,
  });

  factory QuotationItem.fromJson(Map<String, dynamic> json) {
    return QuotationItem(
      id: json['id'] ?? '',
      quotationId: json['quotation_id'] ?? '',
      description: json['description'] ?? '',
      itemType: json['item_type'] ?? 'labor',
      unitPrice: (json['unit_price'] as num?)?.toDouble() ?? 0.0,
      quantity: (json['quantity'] as num?)?.toDouble() ?? 1.0,
      totalPrice: (json['total_price'] as num?)?.toDouble() ?? 0.0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'quotation_id': quotationId,
      'description': description,
      'item_type': itemType,
      'unit_price': unitPrice,
      'quantity': quantity,
      'total_price': totalPrice,
    };
  }
}

class Quotation {
  final String id;
  final String bookingId;
  final String providerProfileId;
  final int version;
  final String? inspectionNotes;
  final double laborCost;
  final double materialCost;
  final double totalQuoteAmount;
  final String status; // submitted, accepted, rejected, superseded, expired
  final DateTime expiresAt;
  final String? customerRejectedReason;
  final List<QuotationItem> items;

  Quotation({
    required this.id,
    required this.bookingId,
    required this.providerProfileId,
    this.version = 1,
    this.inspectionNotes,
    required this.laborCost,
    required this.materialCost,
    required this.totalQuoteAmount,
    required this.status,
    required this.expiresAt,
    this.customerRejectedReason,
    this.items = const [],
  });

  bool get isExpired => DateTime.now().isAfter(expiresAt);
  bool get isAcceptable => status == 'submitted' && !isExpired;

  factory Quotation.fromJson(Map<String, dynamic> json) {
    return Quotation(
      id: json['id'] ?? '',
      bookingId: json['booking_id'] ?? '',
      providerProfileId: json['provider_profile_id'] ?? '',
      version: (json['version'] as num?)?.toInt() ?? 1,
      inspectionNotes: json['inspection_notes'],
      laborCost: (json['labor_cost'] as num?)?.toDouble() ?? 0.0,
      materialCost: (json['material_cost'] as num?)?.toDouble() ?? 0.0,
      totalQuoteAmount: (json['total_quote_amount'] as num?)?.toDouble() ?? 0.0,
      status: json['status'] ?? 'submitted',
      expiresAt: DateTime.parse(json['expires_at']),
      customerRejectedReason: json['customer_rejected_reason'],
      items: (json['items'] as List<dynamic>?)
              ?.map((e) => QuotationItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'booking_id': bookingId,
      'provider_profile_id': providerProfileId,
      'version': version,
      'inspection_notes': inspectionNotes,
      'labor_cost': laborCost,
      'material_cost': materialCost,
      'total_quote_amount': totalQuoteAmount,
      'status': status,
      'expires_at': expiresAt.toIso8601String(),
      'customer_rejected_reason': customerRejectedReason,
      'items': items.map((e) => e.toJson()).toList(),
    };
  }
}
