import 'package:flutter/material.dart';
import 'package:kaamly_shared/kaamly_shared.dart';

class CustomerLoginScreen extends StatefulWidget {
  final ApiClient apiClient;
  final VoidCallback onLoginSuccess;

  const CustomerLoginScreen({
    Key? key,
    required this.apiClient,
    required this.onLoginSuccess,
  }) : super(key: key);

  @override
  State<CustomerLoginScreen> createState() => _CustomerLoginScreenState();
}

class _CustomerLoginScreenState extends State<CustomerLoginScreen> {
  final _phoneController = TextEditingController(text: '+923001234567');
  final _passwordController = TextEditingController(text: 'secret123');
  bool _isLoading = false;
  String? _errorMessage;

  Future<void> _handleLogin() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final res = await widget.apiClient.post(
      ApiEndpoints.login,
      data: {
        'phone_number': _phoneController.text.trim(),
        'password': _passwordController.text.trim(),
      },
    );

    setState(() => _isLoading = false);

    if (res.success && res.data != null) {
      final token = res.data['token'];
      if (token != null) {
        await widget.apiClient.saveToken(token.toString());
        widget.onLoginSuccess();
      }
    } else {
      setState(() {
        _errorMessage = res.message.isNotEmpty ? res.message : 'Invalid credentials';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.settings_outlined, color: AppColors.primary),
            tooltip: 'Server Settings',
            onPressed: () {
              showDialog(
                context: context,
                builder: (ctx) => ServerConfigDialog(apiClient: widget.apiClient),
              );
            },
          ),
        ],
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Icon(Icons.handyman, size: 64, color: AppColors.primary),
              const SizedBox(height: 16),
              const Text(
                'Welcome to Kaamly',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
              ),
              const SizedBox(height: 8),
              const Text(
                'Book trusted electricians, plumbers, and home repair technicians in minutes.',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.textSecondary),
              ),
              const SizedBox(height: 32),
              if (_errorMessage != null) ...[
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.red.shade50,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.red.shade200),
                  ),
                  child: Text(_errorMessage!, style: const TextStyle(color: Colors.red)),
                ),
                const SizedBox(height: 16),
              ],
              TextField(
                controller: _phoneController,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Phone Number',
                  prefixIcon: Icon(Icons.phone),
                ),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _passwordController,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Password',
                  prefixIcon: Icon(Icons.lock),
                ),
              ),
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: _isLoading ? null : _handleLogin,
                child: _isLoading
                    ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Text('Sign In'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
