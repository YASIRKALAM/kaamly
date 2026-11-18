import 'package:flutter/material.dart';
import 'package:kaamly_shared/kaamly_shared.dart';
import 'features/auth/screens/provider_login_screen.dart';
import 'features/onboarding/screens/onboarding_wizard_screen.dart';
import 'features/jobs/screens/provider_jobs_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final apiClient = ApiClient();
  final token = await apiClient.getToken();

  runApp(KaamlyProviderApp(
    apiClient: apiClient,
    initialToken: token,
  ));
}

class KaamlyProviderApp extends StatefulWidget {
  final ApiClient apiClient;
  final String? initialToken;

  const KaamlyProviderApp({
    Key? key,
    required this.apiClient,
    this.initialToken,
  }) : super(key: key);

  @override
  State<KaamlyProviderApp> createState() => _KaamlyProviderAppState();
}

class _KaamlyProviderAppState extends State<KaamlyProviderApp> {
  bool _isAuthenticated = false;
  bool _isOnboarded = true; // Set to true after completing 5-step onboarding

  @override
  void initState() {
    super.initState();
    _isAuthenticated = widget.initialToken != null && widget.initialToken!.isNotEmpty;
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Kaamly Partner',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      home: !_isAuthenticated
          ? ProviderLoginScreen(
              apiClient: widget.apiClient,
              onLoginSuccess: () {
                setState(() => _isAuthenticated = true);
              },
            )
          : !_isOnboarded
              ? OnboardingWizardScreen(
                  apiClient: widget.apiClient,
                  onComplete: () {
                    setState(() => _isOnboarded = true);
                  },
                )
              : ProviderJobsScreen(apiClient: widget.apiClient),
    );
  }
}
