import 'package:flutter/material.dart';
import 'package:kaamly_shared/kaamly_shared.dart';

class BookingTrackingScreen extends StatefulWidget {
  final String bookingId;
  final ApiClient apiClient;

  const BookingTrackingScreen({
    Key? key,
    required this.bookingId,
    required this.apiClient,
  }) : super(key: key);

  @override
  State<BookingTrackingScreen> createState() => _BookingTrackingScreenState();
}

class _BookingTrackingScreenState extends State<BookingTrackingScreen> {
  Booking? _booking;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchDetails();
  }

  Future<void> _fetchDetails() async {
    setState(() => _isLoading = true);

    final res = await widget.apiClient.get<Booking>(
      ApiEndpoints.customerBookingDetails(widget.bookingId),
      fromJsonT: (json) => Booking.fromJson(json),
    );

    setState(() {
      _booking = res.data;
      _isLoading = false;
    });
  }

  Future<void> _confirmFixedPrice() async {
    final res = await widget.apiClient.post(
      ApiEndpoints.customerConfirmBooking(widget.bookingId),
    );
    if (res.success) {
      _fetchDetails();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res.message)));
    }
  }

  Future<void> _acceptQuotation(Quotation quote) async {
    final res = await widget.apiClient.post(
      ApiEndpoints.customerAcceptQuotation(widget.bookingId, quote.id),
      data: {'payment_mode': _booking?.paymentMode ?? 'online_escrow'},
    );
    if (res.success) {
      _fetchDetails();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res.message)));
    }
  }

  Future<void> _cancelBooking() async {
    final res = await widget.apiClient.post(
      ApiEndpoints.customerCancelBooking(widget.bookingId),
      data: {'reason': 'Customer requested cancellation'},
    );
    if (res.success) {
      _fetchDetails();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res.message)));
    }
  }

  Widget _buildStatusChip(String status) {
    Color bg = Colors.grey.shade200;
    Color fg = Colors.grey.shade800;

    if (['confirmed', 'in_progress', 'completed'].contains(status)) {
      bg = Colors.teal.shade50;
      fg = AppColors.primary;
    } else if (['quotation_submitted', 'awaiting_customer_confirmation', 'rescheduled'].contains(status)) {
      bg = Colors.amber.shade50;
      fg = Colors.amber.shade900;
    } else if (['cancelled', 'no_show', 'disputed'].contains(status)) {
      bg = Colors.red.shade50;
      fg = Colors.red.shade800;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(20)),
      child: Text(
        status.replaceAll('_', ' ').toUpperCase(),
        style: TextStyle(color: fg, fontWeight: FontWeight.bold, fontSize: 12),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (_booking == null) {
      return const Scaffold(body: Center(child: Text('Booking not found')));
    }

    final b = _booking!;
    final pendingQuote = b.quotations.where((q) => q.isAcceptable).firstOrNull;

    return Scaffold(
      appBar: AppBar(
        title: Text(b.bookingNumber),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _fetchDetails),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Status Header
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(b.service?.name ?? 'Service', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                      _buildStatusChip(b.status),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text('Price: PKR ${b.agreedTotalPrice.toStringAsFixed(0)} (${b.paymentMode == 'online_escrow' ? 'Escrow' : 'COD'})'),
                  if (b.cancellationFee > 0)
                    Text('Late Cancellation Fee: PKR ${b.cancellationFee.toStringAsFixed(0)}', style: const TextStyle(color: Colors.red)),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),

          // Quotation Approval Card (if awaiting customer confirmation)
          if (b.status == 'awaiting_customer_confirmation' && pendingQuote != null) ...[
            Card(
              color: Colors.amber.shade50,
              shape: RoundedRectangleBorder(
                side: BorderSide(color: Colors.amber.shade300),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Inspection Quotation', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                        Text('v${pendingQuote.version}', style: const TextStyle(fontWeight: FontWeight.bold)),
                      ],
                    ),
                    const Divider(),
                    if (pendingQuote.inspectionNotes != null) ...[
                      Text('Notes: ${pendingQuote.inspectionNotes!}'),
                      const SizedBox(height: 8),
                    ],
                    ...pendingQuote.items.map((it) => Padding(
                          padding: const EdgeInsets.symmetric(vertical: 2),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text('${it.description} (${it.itemType})'),
                              Text('PKR ${it.totalPrice.toStringAsFixed(0)}'),
                            ],
                          ),
                        )),
                    const Divider(),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Total Quote:', style: TextStyle(fontWeight: FontWeight.bold)),
                        Text('PKR ${pendingQuote.totalQuoteAmount.toStringAsFixed(0)}',
                            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppColors.primary)),
                      ],
                    ),
                    const SizedBox(height: 12),
                    ElevatedButton(
                      onPressed: () => _acceptQuotation(pendingQuote),
                      child: const Text('Approve & Lock Price'),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],

          // Action Buttons
          if (b.status == 'accepted') ...[
            ElevatedButton(
              onPressed: _confirmFixedPrice,
              child: const Text('Confirm Booking'),
            ),
            const SizedBox(height: 8),
          ],

          if (['accepted', 'confirmed', 'provider_on_the_way'].contains(b.status)) ...[
            OutlinedButton(
              style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
              onPressed: _cancelBooking,
              child: Text(b.status == 'provider_on_the_way'
                  ? 'Cancel (Late PKR 500 Travel Fee Appling)'
                  : 'Cancel Booking'),
            ),
          ],

          // Address & Schedules
          const SizedBox(height: 16),
          const Text('Address & Schedule Details', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Address: ${b.address?.fullAddress ?? ''}'),
                  const SizedBox(height: 6),
                  Text('Scheduled: ${b.scheduledStartAt.toLocal().toString().substring(0, 16)}'),
                  if (b.actualStartedAt != null)
                    Text('Technician Started: ${b.actualStartedAt!.toLocal().toString().substring(0, 16)}'),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
