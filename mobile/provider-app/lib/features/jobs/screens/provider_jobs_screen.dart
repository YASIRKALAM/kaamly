import 'package:flutter/material.dart';
import 'package:kaamly_shared/kaamly_shared.dart';
import 'submit_quotation_screen.dart';
import '../../wallet/screens/provider_wallet_screen.dart';

class ProviderJobsScreen extends StatefulWidget {
  final ApiClient apiClient;

  const ProviderJobsScreen({Key? key, required this.apiClient}) : super(key: key);

  @override
  State<ProviderJobsScreen> createState() => _ProviderJobsScreenState();
}

class _ProviderJobsScreenState extends State<ProviderJobsScreen> {
  List<Booking> _jobs = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchJobs();
  }

  Future<void> _fetchJobs() async {
    setState(() => _isLoading = true);

    final res = await widget.apiClient.get<List<Booking>>(
      ApiEndpoints.providerJobs,
      fromJsonT: (json) => (json['data'] as List).map((e) => Booking.fromJson(e)).toList(),
    );

    setState(() {
      _jobs = res.data ?? [];
      _isLoading = false;
    });
  }

  Future<void> _updateJobStatus(String jobId, String action) async {
    String endpoint;
    if (action == 'accept') {
      endpoint = ApiEndpoints.providerAcceptJob(jobId);
    } else if (action == 'on-the-way') {
      endpoint = ApiEndpoints.providerOnTheWay(jobId);
    } else if (action == 'start') {
      endpoint = ApiEndpoints.providerStartJob(jobId);
    } else if (action == 'complete') {
      endpoint = ApiEndpoints.providerCompleteJob(jobId);
    } else {
      return;
    }

    final res = await widget.apiClient.post(endpoint);
    if (res.success) {
      _fetchJobs();
    } else {
      showDialog(
        context: context,
        builder: (_) => AlertDialog(
          title: const Text('Action Blocked'),
          content: Text(res.message),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('OK'),
            ),
          ],
        ),
      );
    }
  }

  void _showNoShowDialog(Booking job) {
    int waitMinutes = 25;
    int callsLogged = 3;
    final reasonController = TextEditingController(text: 'Customer did not open door and calls went unanswered.');

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Report Customer No-Show'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Requirements: At least 20 minutes wait time on-site and 2 logged phone calls.',
              style: TextStyle(fontSize: 12, color: Colors.grey),
            ),
            const SizedBox(height: 12),
            Text('Wait Time: $waitMinutes minutes'),
            Text('Calls Logged: $callsLogged calls'),
            const SizedBox(height: 12),
            TextField(
              controller: reasonController,
              decoration: const InputDecoration(labelText: 'Explanation & Details'),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () async {
              Navigator.pop(ctx);
              final res = await widget.apiClient.post(
                ApiEndpoints.providerReportNoShow(job.id),
                data: {
                  'responsible_party': 'customer',
                  'reason': reasonController.text,
                  'wait_minutes': waitMinutes,
                  'calls_logged': callsLogged,
                },
              );
              if (res.success) {
                _fetchJobs();
              } else {
                ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res.message)));
              }
            },
            child: const Text('Confirm No-Show & Claim Travel Fee'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Technician Portal'),
        actions: [
          IconButton(
            icon: const Icon(Icons.account_balance_wallet),
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => ProviderWalletScreen(apiClient: widget.apiClient),
                ),
              );
            },
          ),
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _fetchJobs,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _jobs.isEmpty
              ? const Center(child: Text('No assigned jobs currently.'))
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: _jobs.length,
                  itemBuilder: (context, index) {
                    final job = _jobs[index];
                    return Card(
                      margin: const EdgeInsets.only(bottom: 16),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(job.bookingNumber, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                                Chip(
                                  label: Text(
                                    job.status.replaceAll('_', ' ').toUpperCase(),
                                    style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Text('Service: ${job.service?.name ?? ''}', style: const TextStyle(fontWeight: FontWeight.w600)),
                            Text('Customer Address: ${job.address?.fullAddress ?? ''}'),
                            Text('Payment: ${job.paymentMode.toUpperCase()} | Agreed: PKR ${job.agreedTotalPrice.toStringAsFixed(0)}'),
                            const Divider(height: 24),

                            // Dynamic Actions per State
                            Wrap(
                              spacing: 8,
                              children: [
                                if (job.status == 'pending' || job.status == 'broadcasted')
                                  ElevatedButton(
                                    onPressed: () => _updateJobStatus(job.id, 'accept'),
                                    child: const Text('Accept Job'),
                                  ),
                                if (job.status == 'accepted') ...[
                                  ElevatedButton(
                                    onPressed: () {
                                      Navigator.push(
                                        context,
                                        MaterialPageRoute(
                                          builder: (_) => SubmitQuotationScreen(
                                            bookingId: job.id,
                                            apiClient: widget.apiClient,
                                            onQuotationSubmitted: _fetchJobs,
                                          ),
                                        ),
                                      );
                                    },
                                    style: ElevatedButton.styleFrom(backgroundColor: AppColors.accent),
                                    child: const Text('Submit Inspection Quote'),
                                  ),
                                  ElevatedButton(
                                    onPressed: () => _updateJobStatus(job.id, 'on-the-way'),
                                    child: const Text('On The Way'),
                                  ),
                                ],
                                if (job.status == 'confirmed')
                                  ElevatedButton(
                                    onPressed: () => _updateJobStatus(job.id, 'on-the-way'),
                                    child: const Text('Start Travel (On The Way)'),
                                  ),
                                if (job.status == 'provider_on_the_way') ...[
                                  ElevatedButton(
                                    onPressed: () => _updateJobStatus(job.id, 'start'),
                                    child: const Text('Arrived (Start Work)'),
                                  ),
                                  OutlinedButton(
                                    style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
                                    onPressed: () => _showNoShowDialog(job),
                                    child: const Text('Report Customer No-Show'),
                                  ),
                                ],
                                if (job.status == 'in_progress')
                                  ElevatedButton(
                                    style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
                                    onPressed: () => _updateJobStatus(job.id, 'complete'),
                                    child: const Text('Complete Job'),
                                  ),
                              ],
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
