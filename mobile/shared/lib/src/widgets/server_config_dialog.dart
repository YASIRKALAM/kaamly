import 'package:flutter/material.dart';
import '../network/api_client.dart';
import '../theme/app_theme.dart';

class ServerConfigDialog extends StatefulWidget {
  final ApiClient apiClient;
  final VoidCallback? onSaved;

  const ServerConfigDialog({
    Key? key,
    required this.apiClient,
    this.onSaved,
  }) : super(key: key);

  @override
  State<ServerConfigDialog> createState() => _ServerConfigDialogState();
}

class _ServerConfigDialogState extends State<ServerConfigDialog> {
  late final TextEditingController _urlController;
  bool _isTesting = false;
  String? _testResult;
  bool? _isSuccess;

  @override
  void initState() {
    super.initState();
    _urlController = TextEditingController(text: widget.apiClient.currentBaseUrl);
  }

  @override
  void dispose() {
    _urlController.dispose();
    super.dispose();
  }

  Future<void> _handleTest() async {
    setState(() {
      _isTesting = true;
      _testResult = null;
      _isSuccess = null;
    });

    // Temporarily set URL to test
    final originalUrl = widget.apiClient.currentBaseUrl;
    await widget.apiClient.updateBaseUrl(_urlController.text.trim());
    final success = await widget.apiClient.testConnection();

    if (!success) {
      // Revert if test failed
      await widget.apiClient.updateBaseUrl(originalUrl);
    }

    setState(() {
      _isTesting = false;
      _isSuccess = success;
      _testResult = success
          ? 'Connected to Kaamly Server successfully!'
          : 'Could not reach server. Verify IP, port, and Wi-Fi.';
    });
  }

  Future<void> _handleSave() async {
    final url = _urlController.text.trim();
    if (url.isNotEmpty) {
      await widget.apiClient.updateBaseUrl(url);
      if (mounted) {
        widget.onSaved?.call();
        Navigator.of(context).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Server URL set to: $url'),
            backgroundColor: AppColors.success,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Row(
        children: [
          Icon(Icons.settings, color: AppColors.primary),
          SizedBox(width: 8),
          Text('Server Configuration', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
        ],
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Set the Kaamly backend API base URL for this app:',
              style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _urlController,
              decoration: const InputDecoration(
                labelText: 'API Base URL',
                hintText: 'http://192.168.18.9:8080/api/v1',
                prefixIcon: Icon(Icons.link),
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 6,
              children: [
                ActionChip(
                  avatar: const Icon(Icons.wifi, size: 16),
                  label: const Text('Wi-Fi (192.168.18.9)', style: TextStyle(fontSize: 11)),
                  onPressed: () {
                    _urlController.text = 'http://192.168.18.9:8080/api/v1';
                  },
                ),
                ActionChip(
                  avatar: const Icon(Icons.computer, size: 16),
                  label: const Text('Emulator (10.0.2.2)', style: TextStyle(fontSize: 11)),
                  onPressed: () {
                    _urlController.text = 'http://10.0.2.2:8080/api/v1';
                  },
                ),
              ],
            ),
            const SizedBox(height: 12),
            if (_isTesting)
              const Center(
                child: Padding(
                  padding: EdgeInsets.symmetric(vertical: 8),
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
              )
            else if (_testResult != null)
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: _isSuccess == true ? Colors.green.shade50 : Colors.red.shade50,
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(
                    color: _isSuccess == true ? Colors.green.shade300 : Colors.red.shade300,
                  ),
                ),
                child: Row(
                  children: [
                    Icon(
                      _isSuccess == true ? Icons.check_circle : Icons.error,
                      color: _isSuccess == true ? Colors.green : Colors.red,
                      size: 18,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        _testResult!,
                        style: TextStyle(
                          fontSize: 12,
                          color: _isSuccess == true ? Colors.green.shade900 : Colors.red.shade900,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _isTesting ? null : _handleTest,
          child: const Text('Test Ping'),
        ),
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Cancel'),
        ),
        ElevatedButton(
          onPressed: _handleSave,
          style: ElevatedButton.styleFrom(
            backgroundColor: AppColors.primary,
            foregroundColor: Colors.white,
          ),
          child: const Text('Save & Apply'),
        ),
      ],
    );
  }
}
