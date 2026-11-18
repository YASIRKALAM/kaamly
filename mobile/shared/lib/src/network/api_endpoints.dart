class ApiEndpoints {
  static const String baseUrl = 'http://10.0.2.2:8080/api/v1'; // Android emulator localhost alias

  // Auth
  static const String register = '/auth/register';
  static const String login = '/auth/login';
  static const String sendOtp = '/auth/send-otp';
  static const String verifyOtp = '/auth/verify-otp';
  static const String me = '/auth/me';
  static const String logout = '/auth/logout';

  // Catalog
  static const String categories = '/catalog/categories';
  static const String services = '/catalog/services';

  // Discovery
  static const String discoveryProviders = '/discovery/providers';

  // Customer
  static const String customerAddresses = '/customer/addresses';
  static const String customerBookings = '/customer/bookings';
  static String customerBookingDetails(String id) => '/customer/bookings/$id';
  static String customerConfirmBooking(String id) => '/customer/bookings/$id/confirm';
  static String customerCancelBooking(String id) => '/customer/bookings/$id/cancel';
  static String customerAcceptQuotation(String bookingId, String quoteId) =>
      '/customer/bookings/$bookingId/quotations/$quoteId/accept';
  static String customerRejectQuotation(String bookingId, String quoteId) =>
      '/customer/bookings/$bookingId/quotations/$quoteId/reject';
  static String customerRequestReschedule(String id) => '/customer/bookings/$id/reschedule/request';
  static String customerConfirmReschedule(String id) => '/customer/bookings/$id/reschedule/confirm';
  static String customerReportNoShow(String id) => '/customer/bookings/$id/no-show';

  // Provider
  static const String providerOnboardingProfile = '/provider/onboarding/profile';
  static const String providerOnboardingDocuments = '/provider/onboarding/documents';
  static const String providerOnboardingServices = '/provider/onboarding/services';
  static const String providerOnboardingWorkingHours = '/provider/onboarding/working-hours';
  static const String providerOnboardingBankAccount = '/provider/onboarding/bank-account';
  static const String providerOnboardingSubmit = '/provider/onboarding/submit-review';

  static const String providerLocation = '/provider/location';
  static const String providerAvailability = '/provider/availability';
  static const String providerJobs = '/provider/jobs';
  static String providerAcceptJob(String id) => '/provider/jobs/$id/accept';
  static String providerSubmitQuotation(String id) => '/provider/jobs/$id/quotation';
  static String providerOnTheWay(String id) => '/provider/jobs/$id/on-the-way';
  static String providerStartJob(String id) => '/provider/jobs/$id/start';
  static String providerCompleteJob(String id) => '/provider/jobs/$id/complete';
  static String providerCancelJob(String id) => '/provider/jobs/$id/cancel';
  static String providerRequestReschedule(String id) => '/provider/jobs/$id/reschedule/request';
  static String providerConfirmReschedule(String id) => '/provider/jobs/$id/reschedule/confirm';
  static String providerReportNoShow(String id) => '/provider/jobs/$id/no-show';
  static const String providerWallet = '/provider/wallet';

  // Reviews & Disputes
  static String submitReview(String bookingId) => '/bookings/$bookingId/reviews';
  static String raiseDispute(String bookingId) => '/bookings/$bookingId/disputes';
}
