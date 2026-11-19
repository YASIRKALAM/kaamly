import 'package:flutter/material.dart';
import 'package:kaamly_shared/kaamly_shared.dart';
import 'features/auth/screens/login_screen.dart';
import 'features/home/screens/home_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final apiClient = ApiClient();
  await apiClient.loadSavedBaseUrl();
  final token = await apiClient.getToken();

  runApp(KaamlyCustomerApp(
    apiClient: apiClient,
    initialToken: token,
  ));
}

class KaamlyCustomerApp extends StatefulWidget {
  final ApiClient apiClient;
  final String? initialToken;

  const KaamlyCustomerApp({
    Key? key,
    required this.apiClient,
    this.initialToken,
  }) : super(key: key);

  @override
  State<KaamlyCustomerApp> createState() => _KaamlyCustomerAppState();
}

class _KaamlyCustomerAppState extends State<KaamlyCustomerApp> {
  bool _isAuthenticated = false;

  @override
  void initState() {
    super.initState();
    _isAuthenticated = widget.initialToken != null && widget.initialToken!.isNotEmpty;
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Kaamly Customer',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      home: _isAuthenticated
          ? CustomerHomeScreen(apiClient: widget.apiClient)
          : CustomerLoginScreen(
              apiClient: widget.apiClient,
              onLoginSuccess: () {
                setState(() => _isAuthenticated = true);
              },
            ),
    );
  }
}
