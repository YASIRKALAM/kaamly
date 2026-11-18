<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Kaamly Centralized REST API Routes (/api/v1)
|--------------------------------------------------------------------------
*/

// 1. Health & Status
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'status' => 'healthy',
        'service' => 'kaamly-api',
        'version' => '1.0.0',
        'framework' => 'Laravel 13',
        'timestamp' => now()->toISOString(),
    ]);
});

// 2. Authentication & Identity
Route::prefix('auth')->group(function () {
    Route::post('/register', [\App\Http\Controllers\Api\V1\AuthController::class, 'register']);
    Route::post('/login', [\App\Http\Controllers\Api\V1\AuthController::class, 'login']);
    Route::post('/send-otp', [\App\Http\Controllers\Api\V1\AuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [\App\Http\Controllers\Api\V1\AuthController::class, 'verifyOtp']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [\App\Http\Controllers\Api\V1\AuthController::class, 'me']);
        Route::post('/logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
    });
});

// 3. Public Service Catalog
Route::prefix('catalog')->group(function () {
    Route::get('/categories', [\App\Http\Controllers\Api\V1\CatalogController::class, 'categories']);
    Route::get('/services', [\App\Http\Controllers\Api\V1\CatalogController::class, 'services']);
    Route::get('/services/{slug}', [\App\Http\Controllers\Api\V1\CatalogController::class, 'showService']);
});

// 4. Spatial Provider Discovery Engine (PostGIS)
Route::post('/discovery/providers', [\App\Http\Controllers\Api\V1\DiscoveryController::class, 'searchProviders']);

// 5. Customer Domain Routes
Route::prefix('customer')->middleware(['auth:sanctum', 'role:customer'])->group(function () {
    Route::get('/addresses', [\App\Http\Controllers\Api\V1\CustomerController::class, 'listAddresses']);
    Route::post('/addresses', [\App\Http\Controllers\Api\V1\CustomerController::class, 'createAddress']);
    
    Route::get('/bookings', [\App\Http\Controllers\Api\V1\BookingController::class, 'customerBookings']);
    Route::post('/bookings', [\App\Http\Controllers\Api\V1\BookingController::class, 'createBooking']);
    Route::get('/bookings/{id}', [\App\Http\Controllers\Api\V1\BookingController::class, 'showBooking']);
    Route::post('/bookings/{id}/confirm', [\App\Http\Controllers\Api\V1\BookingController::class, 'confirmBooking']);
    Route::post('/bookings/{id}/cancel', [\App\Http\Controllers\Api\V1\BookingController::class, 'cancelBooking']);
    Route::post('/bookings/{id}/quotations/{quoteId}/accept', [\App\Http\Controllers\Api\V1\BookingController::class, 'acceptQuotation']);
    Route::post('/bookings/{id}/quotations/{quoteId}/reject', [\App\Http\Controllers\Api\V1\BookingController::class, 'rejectQuotation']);
    Route::post('/bookings/{id}/reschedule/request', [\App\Http\Controllers\Api\V1\BookingController::class, 'requestReschedule']);
    Route::post('/bookings/{id}/reschedule/confirm', [\App\Http\Controllers\Api\V1\BookingController::class, 'confirmReschedule']);
    Route::post('/bookings/{id}/no-show', [\App\Http\Controllers\Api\V1\BookingController::class, 'reportNoShow']);
});

// 6. Provider Domain Routes
Route::prefix('provider')->middleware(['auth:sanctum', 'role:provider'])->group(function () {
    // Onboarding Wizard (Steps 1 to 5)
    Route::prefix('onboarding')->group(function () {
        Route::post('/profile', [\App\Http\Controllers\Api\V1\ProviderController::class, 'updateOnboardingProfile']);
        Route::post('/documents', [\App\Http\Controllers\Api\V1\ProviderController::class, 'uploadDocument']);
        Route::post('/services', [\App\Http\Controllers\Api\V1\ProviderController::class, 'configureServices']);
        Route::post('/working-hours', [\App\Http\Controllers\Api\V1\ProviderController::class, 'setWorkingHours']);
        Route::post('/bank-account', [\App\Http\Controllers\Api\V1\ProviderController::class, 'setBankAccount']);
        Route::post('/submit-review', [\App\Http\Controllers\Api\V1\ProviderController::class, 'submitForReview']);
    });

    // Real-Time Ops
    Route::post('/location', [\App\Http\Controllers\Api\V1\ProviderController::class, 'updateLocation']);
    Route::post('/availability', [\App\Http\Controllers\Api\V1\ProviderController::class, 'setAvailability']);
    
    // Booking Ops
    Route::get('/jobs', [\App\Http\Controllers\Api\V1\BookingController::class, 'providerJobs']);
    Route::post('/jobs/{id}/accept', [\App\Http\Controllers\Api\V1\BookingController::class, 'acceptJob']);
    Route::post('/jobs/{id}/quotation', [\App\Http\Controllers\Api\V1\BookingController::class, 'submitQuotation']);
    Route::post('/jobs/{id}/on-the-way', [\App\Http\Controllers\Api\V1\BookingController::class, 'onTheWay']);
    Route::post('/jobs/{id}/start', [\App\Http\Controllers\Api\V1\BookingController::class, 'startJob']);
    Route::post('/jobs/{id}/complete', [\App\Http\Controllers\Api\V1\BookingController::class, 'completeJob']);
    Route::post('/jobs/{id}/cancel', [\App\Http\Controllers\Api\V1\BookingController::class, 'cancelBooking']);
    Route::post('/jobs/{id}/reschedule/request', [\App\Http\Controllers\Api\V1\BookingController::class, 'requestReschedule']);
    Route::post('/jobs/{id}/reschedule/confirm', [\App\Http\Controllers\Api\V1\BookingController::class, 'confirmReschedule']);
    Route::post('/jobs/{id}/no-show', [\App\Http\Controllers\Api\V1\BookingController::class, 'reportNoShow']);
    Route::get('/wallet', [\App\Http\Controllers\Api\V1\ProviderController::class, 'walletSummary']);
});

// 7. Reviews & Complaints & Disputes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/bookings/{id}/reviews', [\App\Http\Controllers\Api\V1\ReviewController::class, 'submitReview']);
    Route::post('/bookings/{id}/disputes', [\App\Http\Controllers\Api\V1\ReviewController::class, 'raiseDispute']);
});

// 8. Finance & Operations Admin Routes
Route::prefix('admin')->middleware(['auth:sanctum', 'role:super_admin,finance_manager,operations_manager'])->group(function () {
    // Ledger & Balance Audit
    Route::get('/ledger/accounts', [\App\Http\Controllers\Api\V1\FinanceController::class, 'chartOfAccounts']);
    Route::get('/ledger/transactions', [\App\Http\Controllers\Api\V1\FinanceController::class, 'ledgerTransactions']);
    Route::post('/ledger/reversal', [\App\Http\Controllers\Api\V1\FinanceController::class, 'reverseTransaction']);
    
    // Settlements
    Route::get('/settlements', [\App\Http\Controllers\Api\V1\FinanceController::class, 'listSettlements']);
    Route::post('/settlements/batch', [\App\Http\Controllers\Api\V1\FinanceController::class, 'createSettlementBatch']);
    Route::post('/settlements/{id}/process', [\App\Http\Controllers\Api\V1\FinanceController::class, 'processSettlement']);

    // Provider Approvals
    Route::post('/providers/{id}/documents/{docId}/review', [\App\Http\Controllers\Api\V1\ProviderController::class, 'adminReviewDocument']);
    Route::post('/providers/{id}/approve', [\App\Http\Controllers\Api\V1\ProviderController::class, 'adminApproveProvider']);

    // Dispute Arbitration
    Route::post('/disputes/{id}/arbitrate', [\App\Http\Controllers\Api\V1\ReviewController::class, 'arbitrateDispute']);
});

// 9. In-App Messaging
Route::prefix('chat')->middleware('auth:sanctum')->group(function () {
    Route::get('/conversations', [\App\Http\Controllers\Api\V1\ChatController::class, 'listConversations']);
    Route::get('/conversations/{id}/messages', [\App\Http\Controllers\Api\V1\ChatController::class, 'getMessages']);
    Route::post('/conversations/{id}/messages', [\App\Http\Controllers\Api\V1\ChatController::class, 'sendMessage']);
});
