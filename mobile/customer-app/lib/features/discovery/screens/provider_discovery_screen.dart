import 'package:flutter/material.dart';
import 'package:kaamly_shared/kaamly_shared.dart';
import '../../booking/screens/create_booking_screen.dart';

class ProviderDiscoveryScreen extends StatefulWidget {
  final Service service;
  final ApiClient apiClient;

  const ProviderDiscoveryScreen({
    Key? key,
    required this.service,
    required this.apiClient,
  }) : super(key: key);

  @override
  State<ProviderDiscoveryScreen> createState() => _ProviderDiscoveryScreenState();
}

class _ProviderDiscoveryScreenState extends State<ProviderDiscoveryScreen> {
  List<dynamic> _candidates = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchProviders();
  }

  Future<void> _fetchProviders() async {
    setState(() => _isLoading = true);

    // Call 6-Gate Discovery Endpoint (defaulting to Lahore coordinates)
    final res = await widget.apiClient.post(
      ApiEndpoints.discoveryProviders,
      data: {
        'service_id': widget.service.id,
        'latitude': 31.5204,
        'longitude': 74.3587,
        'radius_km': 20.0,
      },
    );

    setState(() {
      _candidates = res.data != null ? res.data as List : [];
      _isLoading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Technicians for ${widget.service.name}'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _candidates.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.location_off, size: 64, color: Colors.grey),
                      const SizedBox(height: 16),
                      const Text(
                        'No technicians found nearby.',
                        style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 8),
                      const Text('You can still broadcast your request to the entire city.'),
                      const SizedBox(height: 16),
                      ElevatedButton(
                        onPressed: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => CreateBookingScreen(
                                service: widget.service,
                                providerId: null,
                                apiClient: widget.apiClient,
                              ),
                            ),
                          );
                        },
                        child: const Text('Broadcast Booking Request'),
                      )
                    ],
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: _candidates.length,
                  itemBuilder: (context, index) {
                    final p = _candidates[index];
                    final name = '${p['first_name'] ?? ''} ${p['last_name'] ?? ''}'.trim();
                    final distance = p['distance_km'] ?? '0.0';
                    final rating = p['rating_average'] ?? '5.0';
                    final jobs = p['completed_jobs_count'] ?? 0;

                    return Card(
                      margin: const EdgeInsets.only(bottom: 12),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Row(
                          children: [
                            CircleAvatar(
                              backgroundColor: AppColors.primaryLight,
                              child: const Icon(Icons.person, color: AppColors.primaryDark),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                                  const SizedBox(height: 4),
                                  Row(
                                    children: [
                                      const Icon(Icons.star, color: Colors.amber, size: 16),
                                      const SizedBox(width: 4),
                                      Text('$rating ($jobs jobs)'),
                                      const SizedBox(width: 12),
                                      const Icon(Icons.location_on, color: Colors.grey, size: 16),
                                      Text('$distance km away'),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                            ElevatedButton(
                              style: ElevatedButton.styleFrom(
                                minimumSize: const Size(70, 36),
                                padding: const EdgeInsets.symmetric(horizontal: 10),
                              ),
                              onPressed: () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => CreateBookingScreen(
                                      service: widget.service,
                                      providerId: p['provider_id'],
                                      providerName: name,
                                      apiClient: widget.apiClient,
                                    ),
                                  ),
                                );
                              },
                              child: const Text('Select'),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
    );
  }
}
