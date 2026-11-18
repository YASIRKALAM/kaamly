import 'package:flutter/material.dart';
import 'package:kaamly_shared/kaamly_shared.dart';
import '../../discovery/screens/provider_discovery_screen.dart';
import '../../booking/screens/booking_tracking_screen.dart';

class CustomerHomeScreen extends StatefulWidget {
  final ApiClient apiClient;

  const CustomerHomeScreen({Key? key, required this.apiClient}) : super(key: key);

  @override
  State<CustomerHomeScreen> createState() => _CustomerHomeScreenState();
}

class _CustomerHomeScreenState extends State<CustomerHomeScreen> {
  List<ServiceCategory> _categories = [];
  List<Service> _services = [];
  List<Booking> _activeBookings = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);

    final catRes = await widget.apiClient.get<List<ServiceCategory>>(
      ApiEndpoints.categories,
      fromJsonT: (json) => (json as List).map((e) => ServiceCategory.fromJson(e)).toList(),
    );

    final srvRes = await widget.apiClient.get<List<Service>>(
      ApiEndpoints.services,
      fromJsonT: (json) => (json as List).map((e) => Service.fromJson(e)).toList(),
    );

    final bkRes = await widget.apiClient.get<List<Booking>>(
      ApiEndpoints.customerBookings,
      fromJsonT: (json) => (json['data'] as List).map((e) => Booking.fromJson(e)).toList(),
    );

    setState(() {
      _categories = catRes.data ?? [];
      _services = srvRes.data ?? [];
      _activeBookings = bkRes.data?.where((b) => !b.isTerminal).toList() ?? [];
      _isLoading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Kaamly Services'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadData,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _loadData,
        child: ListView(
          padding: const EdgeInsets.all(16.0),
          children: [
            // Active Bookings Banner
            if (_activeBookings.isNotEmpty) ...[
              const Text('Active Bookings', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              ..._activeBookings.map((b) => Card(
                    color: Colors.teal.shade50,
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text(b.bookingNumber, style: const TextStyle(fontWeight: FontWeight.bold)),
                      subtitle: Text('Status: ${b.status.replaceAll('_', ' ').toUpperCase()}'),
                      trailing: const Icon(Icons.arrow_forward_ios, size: 16),
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => BookingTrackingScreen(
                              bookingId: b.id,
                              apiClient: widget.apiClient,
                            ),
                          ),
                        );
                      },
                    ),
                  )),
              const SizedBox(height: 16),
            ],

            // Categories Grid
            const Text('Categories', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 3,
                childAspectRatio: 1.0,
                crossAxisSpacing: 10,
                mainAxisSpacing: 10,
              ),
              itemCount: _categories.length,
              itemBuilder: (context, index) {
                final cat = _categories[index];
                return Card(
                  elevation: 1,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  child: InkWell(
                    onTap: () {},
                    borderRadius: BorderRadius.circular(10),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.home_repair_service, color: AppColors.primary, size: 28),
                        const SizedBox(height: 6),
                        Text(
                          cat.name,
                          textAlign: TextAlign.center,
                          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),

            const SizedBox(height: 24),
            // Popular Services List
            const Text('Popular Services', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            ..._services.map((srv) => Card(
                  margin: const EdgeInsets.only(bottom: 10),
                  child: ListTile(
                    title: Text(srv.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                    subtitle: Text(
                      srv.isInspectionRequired
                          ? 'Inspection Quote Required'
                          : 'PKR ${srv.basePrice.toStringAsFixed(0)}',
                      style: TextStyle(
                        color: srv.isInspectionRequired ? Colors.amber.shade900 : AppColors.primary,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    trailing: ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        minimumSize: const Size(80, 36),
                        padding: const EdgeInsets.symmetric(horizontal: 12),
                      ),
                      onPressed: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => ProviderDiscoveryScreen(
                              service: srv,
                              apiClient: widget.apiClient,
                            ),
                          ),
                        );
                      },
                      child: const Text('Book', style: TextStyle(fontSize: 13)),
                    ),
                  ),
                )),
          ],
        ),
      ),
    );
  }
}
