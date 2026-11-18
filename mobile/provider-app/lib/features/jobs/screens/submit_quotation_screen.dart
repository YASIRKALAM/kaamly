import 'package:flutter/material.dart';
import 'package:kaamly_shared/kaamly_shared.dart';

class SubmitQuotationScreen extends StatefulWidget {
  final String bookingId;
  final ApiClient apiClient;
  final VoidCallback onQuotationSubmitted;

  const SubmitQuotationScreen({
    Key? key,
    required this.bookingId,
    required this.apiClient,
    required this.onQuotationSubmitted,
  }) : super(key: key);

  @override
  State<SubmitQuotationScreen> createState() => _SubmitQuotationScreenState();
}

class _LineItemRow {
  final TextEditingController description = TextEditingController();
  final TextEditingController unitPrice = TextEditingController();
  final TextEditingController quantity = TextEditingController(text: '1');
  String itemType = 'labor'; // labor, material, equipment
}

class _SubmitQuotationScreenState extends State<SubmitQuotationScreen> {
  final _notesController = TextEditingController(text: 'Inspected system: requires diagnostic service and parts replacement.');
  final List<_LineItemRow> _items = [];
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    // Default initial items
    final item1 = _LineItemRow();
    item1.description.text = 'Diagnostic & Repair Labor';
    item1.unitPrice.text = '1500';
    item1.itemType = 'labor';
    _items.add(item1);

    final item2 = _LineItemRow();
    item2.description.text = 'Replacement Capacitor / Valve';
    item2.unitPrice.text = '1200';
    item2.itemType = 'material';
    _items.add(item2);
  }

  double get _totalQuote {
    double sum = 0.0;
    for (final it in _items) {
      final price = double.tryParse(it.unitPrice.text) ?? 0.0;
      final qty = double.tryParse(it.quantity.text) ?? 1.0;
      sum += (price * qty);
    }
    return sum;
  }

  Future<void> _submit() async {
    if (_items.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please add at least one line item.')));
      return;
    }

    setState(() => _isSubmitting = true);

    final payloadItems = _items.map((it) {
      return {
        'description': it.description.text.trim(),
        'item_type': it.itemType,
        'unit_price': double.tryParse(it.unitPrice.text) ?? 0.0,
        'quantity': double.tryParse(it.quantity.text) ?? 1.0,
      };
    }).toList();

    final res = await widget.apiClient.post(
      ApiEndpoints.providerSubmitQuotation(widget.bookingId),
      data: {
        'inspection_notes': _notesController.text.trim(),
        'line_items': payloadItems,
      },
    );

    setState(() => _isSubmitting = false);

    if (res.success) {
      widget.onQuotationSubmitted();
      Navigator.pop(context);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Inspection Quotation')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(
            controller: _notesController,
            maxLines: 2,
            decoration: const InputDecoration(labelText: 'Inspection Findings & Diagnosis Notes'),
          ),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Line Items', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              TextButton.icon(
                icon: const Icon(Icons.add),
                label: const Text('Add Item'),
                onPressed: () {
                  setState(() {
                    _items.add(_LineItemRow());
                  });
                },
              ),
            ],
          ),
          const SizedBox(height: 8),
          ..._items.asMap().entries.map((entry) {
            final idx = entry.key;
            final it = entry.value;
            return Card(
              margin: const EdgeInsets.only(bottom: 12),
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  children: [
                    Row(
                      children: [
                        Expanded(
                          flex: 3,
                          child: TextField(
                            controller: it.description,
                            decoration: const InputDecoration(labelText: 'Item Description'),
                            onChanged: (_) => setState(() {}),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          flex: 2,
                          child: DropdownButtonFormField<String>(
                            value: it.itemType,
                            decoration: const InputDecoration(labelText: 'Type'),
                            items: const [
                              DropdownMenuItem(value: 'labor', child: Text('Labor')),
                              DropdownMenuItem(value: 'material', child: Text('Material')),
                              DropdownMenuItem(value: 'equipment', child: Text('Equipment')),
                            ],
                            onChanged: (val) => setState(() => it.itemType = val!),
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.delete, color: Colors.red),
                          onPressed: () {
                            setState(() {
                              _items.removeAt(idx);
                            });
                          },
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: it.unitPrice,
                            keyboardType: TextInputType.number,
                            decoration: const InputDecoration(labelText: 'Unit Price (PKR)'),
                            onChanged: (_) => setState(() {}),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: TextField(
                            controller: it.quantity,
                            keyboardType: TextInputType.number,
                            decoration: const InputDecoration(labelText: 'Quantity'),
                            onChanged: (_) => setState(() {}),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            );
          }),
          const SizedBox(height: 16),
          Card(
            color: Colors.teal.shade50,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Total Quotation Amount:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                  Text(
                    'PKR ${_totalQuote.toStringAsFixed(0)}',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: AppColors.primary),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: _isSubmitting ? null : _submit,
            child: _isSubmitting
                ? const CircularProgressIndicator(color: Colors.white)
                : const Text('Send Quotation to Customer'),
          ),
        ],
      ),
    );
  }
}
