import 'package:flutter/material.dart';
import 'package:kaamly_shared/kaamly_shared.dart';
import 'booking_tracking_screen.dart';

class CreateBookingScreen extends StatefulWidget {
  final Service service;
  final String? providerId;
  final String? providerName;
  final ApiClient apiClient;

  const CreateBookingScreen({
    Key? key,
    required this.service,
    this.providerId,
    this.providerName,
    required this.apiClient,
  }) : super(key: key);

  @override
  State<CreateBookingScreen> createState() => _CreateBookingScreenState();
}

class _CreateBookingScreenState extends State<CreateBookingScreen> {
  List<CustomerAddress> _addresses = [];
  CustomerAddress? _selectedAddress;
  DateTime _scheduledTime = DateTime.now().add(const Duration(hours: 2));
  String _paymentMode = 'online_escrow'; // online_escrow or cash_on_delivery
  bool _isLoading = true;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _fetchAddresses();
  }

  Future<void> _fetchAddresses() async {
    setState(() => _isLoading = true);
    final res = await widget.apiClient.get<List<CustomerAddress>>(
      ApiEndpoints.customerAddresses,
      fromJsonT: (json) => (json as List).map((e) => CustomerAddress.fromJson(e)).toList(),
    );

    setState(() {
      _addresses = res.data ?? [];
      if (_addresses.isNotEmpty) {
        _selectedAddress = _addresses.firstWhere((a) => a.isDefault, orElse: () => _addresses.first);
      }
      _isLoading = false;
    });
  }

  Future<void> _submitBooking() async {
    if (_selectedAddress == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please select an address')));
      return;
    }

    setState(() => _isSubmitting = true);

    final res = await widget.apiClient.post(
      ApiEndpoints.customerBookings,
      data: {
        'service_id': widget.service.id,
        'address_id': _selectedAddress!.id,
        'provider_profile_id': widget.providerId,
        'scheduled_start_at': _scheduledTime.toIso8601String(),
        'payment_mode': _paymentMode,
      },
    );

    setState(() => _isSubmitting = false);

    if (res.success && res.data != null) {
      final bookingId = res.data['id'];
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => BookingTrackingScreen(
            bookingId: bookingId,
            apiClient: widget.apiClient,
          ),
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Confirm ${widget.service.name}')),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : Padding(
              padding: const EdgeInsets.all(16.0),
              child: ListView(
                children: [
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(widget.service.name, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 4),
                          Text(
                            widget.service.isInspectionRequired
                                ? 'Diagnostic/Inspection workflow'
                                : 'Fixed Price Service: PKR ${widget.service.basePrice.toStringAsFixed(0)}',
                            style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.bold),
                          ),
                          if (widget.providerName != null) ...[
                            const SizedBox(height: 8),
                            Text('Selected Technician: ${widget.providerName!}', style: const TextStyle(color: Colors.grey)),
                          ],
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Text('Select Service Address', style: TextStyle(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  ..._addresses.map((addr) => RadioListTile<CustomerAddress>(
                        title: Text(addr.addressTitle, style: const TextStyle(fontWeight: FontWeight.bold)),
                        subtitle: Text(addr.fullAddress),
                        value: addr,
                        groupValue: _selectedAddress,
                        onChanged: (val) => setState(() => _selectedAddress = val),
                      )),
                  const SizedBox(height: 16),
                  const Text('Payment Method', style: TextStyle(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  RadioListTile<String>(
                    title: const Text('Online Escrow (JazzCash / Card)'),
                    subtitle: const Text('Payment held safely in escrow until you approve job completion'),
                    value: 'online_escrow',
                    groupValue: _paymentMode,
                    onChanged: (val) => setState(() => _paymentMode = val!),
                  ),
                  RadioListTile<String>(
                    title: const Text('Cash on Delivery (COD)'),
                    subtitle: const Text('Pay technician directly in cash upon job completion'),
                    value: 'cash_on_delivery',
                    groupValue: _paymentMode,
                    onChanged: (val) => setState(() => _paymentMode = val!),
                  ),
                  const SizedBox(height: 24),
                  ElevatedButton(
                    onPressed: _isSubmitting ? null : _submitBooking,
                    child: _isSubmitting
                        ? const CircularProgressIndicator(color: Colors.white)
                        : const Text('Place Booking'),
                  ),
                ],
              ),
            ),
    );
  }
}
