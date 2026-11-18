import 'package:flutter/material.dart';
import 'package:kaamly_shared/kaamly_shared.dart';

class ProviderWalletScreen extends StatefulWidget {
  final ApiClient apiClient;

  const ProviderWalletScreen({Key? key, required this.apiClient}) : super(key: key);

  @override
  State<ProviderWalletScreen> createState() => _ProviderWalletScreenState();
}

class _ProviderWalletScreenState extends State<ProviderWalletScreen> {
  ProviderWallet? _wallet;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchWallet();
  }

  Future<void> _fetchWallet() async {
    setState(() => _isLoading = true);

    final res = await widget.apiClient.get<ProviderWallet>(
      ApiEndpoints.providerWallet,
      fromJsonT: (json) => ProviderWallet.fromJson(json),
    );

    setState(() {
      _wallet = res.data;
      _isLoading = false;
    });
  }

  Future<void> _payDebt() async {
    // Simulate paying off debt via 1Link
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Clear COD Commission Debt'),
        content: Text(
          'Total Outstanding COD Debt: PKR ${_wallet?.outstandingCodCommissionDebt.toStringAsFixed(0)}\n\n'
          'Payment will be processed via 1Link/Kuickpay bill payment to platform bank account.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(ctx);
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Payment simulated: Inflow to Platform Bank (1010) credited to 1030.')),
              );
              _fetchWallet();
            },
            child: const Text('Pay via 1Link'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final w = _wallet!;
    final debt = w.outstandingCodCommissionDebt;
    final threshold = w.codDebtThreshold;
    final isBlocked = w.isDebtBlocked;

    return Scaffold(
      appBar: AppBar(title: const Text('Earnings & Wallet')),
      body: RefreshIndicator(
        onRefresh: _fetchWallet,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // Blocking Warning Banner if debt >= threshold
            if (isBlocked) ...[
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.red.shade50,
                  border: Border.all(color: Colors.red.shade300),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.warning, color: Colors.red, size: 32),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Job Acceptance Suspended',
                            style: TextStyle(fontWeight: FontWeight.bold, color: Colors.red),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Your COD commission debt (PKR ${debt.toStringAsFixed(0)}) has reached the PKR ${threshold.toStringAsFixed(0)} threshold. Please settle your debt to resume receiving jobs.',
                            style: const TextStyle(fontSize: 12),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
            ],

            // Earned Payables Card
            Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Earned Online Payables (Account 2020)', style: TextStyle(color: Colors.grey)),
                    const SizedBox(height: 6),
                    Text(
                      'PKR ${w.earnedPayables.toStringAsFixed(2)}',
                      style: const TextStyle(fontSize: 28, fontWeight: FontWeight.bold, color: AppColors.primary),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Net Withdrawable: PKR ${w.netWithdrawableBalance.toStringAsFixed(2)}',
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // COD Commission Debt Card
            Card(
              shape: RoundedRectangleBorder(
                side: BorderSide(color: debt > 0 ? Colors.amber.shade400 : Colors.grey.shade300),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Outstanding COD Debt (Account 1030)', style: TextStyle(color: Colors.grey)),
                        Text('Limit: PKR ${threshold.toStringAsFixed(0)}', style: const TextStyle(fontSize: 12, color: Colors.grey)),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'PKR ${debt.toStringAsFixed(2)}',
                      style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                        color: debt >= threshold ? Colors.red : (debt > 0 ? Colors.amber.shade900 : Colors.black),
                      ),
                    ),
                    const SizedBox(height: 12),
                    LinearProgressIndicator(
                      value: (debt / threshold).clamp(0.0, 1.0),
                      color: debt >= threshold ? Colors.red : Colors.amber,
                      backgroundColor: Colors.grey.shade200,
                    ),
                    const SizedBox(height: 16),
                    if (debt > 0)
                      ElevatedButton(
                        onPressed: _payDebt,
                        child: const Text('Clear Debt via 1Link'),
                      ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
