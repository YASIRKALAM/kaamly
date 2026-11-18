import 'package:flutter/material.dart';
import 'package:kaamly_shared/kaamly_shared.dart';

class OnboardingWizardScreen extends StatefulWidget {
  final ApiClient apiClient;
  final VoidCallback onComplete;

  const OnboardingWizardScreen({
    Key? key,
    required this.apiClient,
    required this.onComplete,
  }) : super(key: key);

  @override
  State<OnboardingWizardScreen> createState() => _OnboardingWizardScreenState();
}

class _OnboardingWizardScreenState extends State<OnboardingWizardScreen> {
  int _currentStep = 0;
  bool _isSaving = false;

  // Step 1: Profile
  final _businessNameController = TextEditingController(text: 'Fast Electricians & AC Repair');
  final _bioController = TextEditingController(text: 'Certified technician with 8 years experience.');
  final _experienceController = TextEditingController(text: '8');

  // Step 2: Documents
  final _cnicController = TextEditingController(text: '35201-1234567-1');

  // Step 4: Bank Account
  final _bankNameController = TextEditingController(text: 'Meezan Bank');
  final _accountTitleController = TextEditingController(text: 'Ali Khan');
  final _ibanController = TextEditingController(text: 'PK36MEZN0000001234567801');

  Future<void> _submitStep() async {
    setState(() => _isSaving = true);

    if (_currentStep == 0) {
      // Step 1: Save Profile
      await widget.apiClient.post(
        ApiEndpoints.providerOnboardingProfile,
        data: {
          'business_name': _businessNameController.text,
          'bio': _bioController.text,
          'experience_years': int.tryParse(_experienceController.text) ?? 5,
        },
      );
      setState(() => _currentStep = 1);
    } else if (_currentStep == 1) {
      // Step 2: Save Documents (CNIC)
      await widget.apiClient.post(
        ApiEndpoints.providerOnboardingDocuments,
        data: {
          'document_type': 'cnic_front',
          'document_number': _cnicController.text,
          'file_path': 'provider_docs/cnic_front_simulated.jpg',
        },
      );
      setState(() => _currentStep = 2);
    } else if (_currentStep == 2) {
      // Step 3: Working Hours
      await widget.apiClient.post(
        ApiEndpoints.providerOnboardingWorkingHours,
        data: {
          'schedule': [
            {'day_of_week': 1, 'start_time': '09:00', 'end_time': '18:00', 'is_active': true},
            {'day_of_week': 2, 'start_time': '09:00', 'end_time': '18:00', 'is_active': true},
            {'day_of_week': 3, 'start_time': '09:00', 'end_time': '18:00', 'is_active': true},
            {'day_of_week': 4, 'start_time': '09:00', 'end_time': '18:00', 'is_active': true},
            {'day_of_week': 5, 'start_time': '09:00', 'end_time': '18:00', 'is_active': true},
            {'day_of_week': 6, 'start_time': '09:00', 'end_time': '18:00', 'is_active': true},
          ]
        },
      );
      setState(() => _currentStep = 3);
    } else if (_currentStep == 3) {
      // Step 4: Bank Account
      await widget.apiClient.post(
        ApiEndpoints.providerOnboardingBankAccount,
        data: {
          'bank_name': _bankNameController.text,
          'account_title': _accountTitleController.text,
          'account_number_or_iban': _ibanController.text,
        },
      );
      setState(() => _currentStep = 4);
    } else if (_currentStep == 4) {
      // Step 5: Final Submission for Admin Review
      final res = await widget.apiClient.post(ApiEndpoints.providerOnboardingSubmit);
      if (res.success) {
        widget.onComplete();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res.message)));
      }
    }

    setState(() => _isSaving = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Technician Onboarding')),
      body: Stepper(
        currentStep: _currentStep,
        onStepContinue: _isSaving ? null : _submitStep,
        onStepCancel: _currentStep > 0 ? () => setState(() => _currentStep -= 1) : null,
        steps: [
          Step(
            title: const Text('Profile Details'),
            isActive: _currentStep >= 0,
            content: Column(
              children: [
                TextField(controller: _businessNameController, decoration: const InputDecoration(labelText: 'Business / Trade Name')),
                const SizedBox(height: 12),
                TextField(controller: _experienceController, decoration: const InputDecoration(labelText: 'Years of Experience')),
                const SizedBox(height: 12),
                TextField(controller: _bioController, decoration: const InputDecoration(labelText: 'Short Bio')),
              ],
            ),
          ),
          Step(
            title: const Text('Identity & Documents'),
            isActive: _currentStep >= 1,
            content: Column(
              children: [
                TextField(controller: _cnicController, decoration: const InputDecoration(labelText: 'CNIC Number (xxxxx-xxxxxxx-x)')),
                const SizedBox(height: 12),
                const ListTile(
                  leading: Icon(Icons.file_upload, color: AppColors.primary),
                  title: Text('CNIC Front & Back (Uploaded)'),
                  subtitle: Text('Private encrypted S3 storage'),
                ),
              ],
            ),
          ),
          Step(
            title: const Text('Working Schedule'),
            isActive: _currentStep >= 2,
            content: const Text('Default shifts configured: Mon - Sat (9:00 AM - 6:00 PM). Sunday Off.'),
          ),
          Step(
            title: const Text('1Link Bank Account'),
            isActive: _currentStep >= 3,
            content: Column(
              children: [
                TextField(controller: _bankNameController, decoration: const InputDecoration(labelText: 'Bank Name')),
                const SizedBox(height: 12),
                TextField(controller: _accountTitleController, decoration: const InputDecoration(labelText: 'Account Title')),
                const SizedBox(height: 12),
                TextField(controller: _ibanController, decoration: const InputDecoration(labelText: 'IBAN Number (24 chars)')),
              ],
            ),
          ),
          Step(
            title: const Text('Review & Submit'),
            isActive: _currentStep >= 4,
            content: const Text('Please review all details. Once submitted, our operations compliance team will verify your credentials within 24 hours.'),
          ),
        ],
      ),
    );
  }
}
