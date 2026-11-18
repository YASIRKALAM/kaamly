class ServiceCategory {
  final String id;
  final String name;
  final String slug;
  final String? iconUrl;
  final String? description;
  final int displayOrder;

  ServiceCategory({
    required this.id,
    required this.name,
    required this.slug,
    this.iconUrl,
    this.description,
    this.displayOrder = 0,
  });

  factory ServiceCategory.fromJson(Map<String, dynamic> json) {
    return ServiceCategory(
      id: json['id'] ?? '',
      name: json['name'] ?? '',
      slug: json['slug'] ?? '',
      iconUrl: json['icon_url'],
      description: json['description'],
      displayOrder: (json['display_order'] as num?)?.toInt() ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'slug': slug,
      'icon_url': iconUrl,
      'description': description,
      'display_order': displayOrder,
    };
  }
}

class Service {
  final String id;
  final String categoryId;
  final String name;
  final String slug;
  final String? description;
  final String pricingModel; // fixed, hourly, inspection_quote
  final double basePrice;
  final int estimatedDurationMinutes;
  final bool isInspectionRequired;
  final String? imageUrl;
  final ServiceCategory? category;

  Service({
    required this.id,
    required this.categoryId,
    required this.name,
    required this.slug,
    this.description,
    required this.pricingModel,
    required this.basePrice,
    required this.estimatedDurationMinutes,
    required this.isInspectionRequired,
    this.imageUrl,
    this.category,
  });

  factory Service.fromJson(Map<String, dynamic> json) {
    return Service(
      id: json['id'] ?? '',
      categoryId: json['category_id'] ?? '',
      name: json['name'] ?? '',
      slug: json['slug'] ?? '',
      description: json['description'],
      pricingModel: json['pricing_model'] ?? 'fixed',
      basePrice: (json['base_price'] as num?)?.toDouble() ?? 0.0,
      estimatedDurationMinutes: (json['estimated_duration_minutes'] as num?)?.toInt() ?? 60,
      isInspectionRequired: json['is_inspection_required'] ?? false,
      imageUrl: json['image_url'],
      category: json['category'] != null ? ServiceCategory.fromJson(json['category']) : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'category_id': categoryId,
      'name': name,
      'slug': slug,
      'description': description,
      'pricing_model': pricingModel,
      'base_price': basePrice,
      'estimated_duration_minutes': estimatedDurationMinutes,
      'is_inspection_required': isInspectionRequired,
      'image_url': imageUrl,
      'category': category?.toJson(),
    };
  }
}
