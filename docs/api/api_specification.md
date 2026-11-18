# KAAMLY PLATFORM – REST API ARCHITECTURE SPECIFICATION

Version: `v1`  
Protocol: `HTTPS`  
Base URL: `/api/v1`  
Authentication: Bearer Token via Laravel Sanctum  
Response Standard: Unified JSON API Envelope (`success`, `data`, `meta`, `error`)

---

## 1. Unified Response Format

### Success Response (HTTP 200 / 201)
```json
{
  "success": true,
  "message": "Resource retrieved successfully",
  "data": {},
  "meta": {
    "timestamp": "2026-11-16T10:30:00Z",
    "version": "v1"
  }
}
```

### Error Response (HTTP 400 / 401 / 403 / 404 / 422 / 500)
```json
{
  "success": false,
  "message": "Validation failed",
  "error": {
    "code": "VALIDATION_ERROR",
    "details": {
      "phone_number": ["The phone number field is required."]
    }
  },
  "meta": {
    "timestamp": "2026-11-16T10:30:00Z",
    "version": "v1"
  }
}
```

---

## 2. Core API Route Groups

### A. Authentication & Onboarding (`/api/v1/auth`)
* `POST /auth/send-otp` - Send 6-digit SMS verification code
* `POST /auth/verify-otp` - Verify code and issue authentication token
* `POST /auth/register` - Complete customer or provider profile registration
* `POST /auth/login` - Password or OTP login
* `POST /auth/logout` - Revoke current device token
* `GET  /auth/me` - Retrieve current authenticated user profile, roles, and permissions

### B. Customer Workflows (`/api/v1/customer`)
* `GET    /customer/categories` - List active service categories
* `GET    /customer/services` - Search services by category or keyword
* `POST   /customer/providers/nearby` - Spatial search for online providers (latitude, longitude, service_id)
* `GET    /customer/providers/{id}` - Public provider profile, rating breakdowns, approved prices
* `GET    /customer/addresses` - List customer addresses
* `POST   /customer/addresses` - Add new address with PostGIS latitude/longitude
* `POST   /customer/bookings` - Create a new booking request
* `GET    /customer/bookings` - List customer bookings (with status filters)
* `GET    /customer/bookings/{id}` - Complete booking timeline, quotation & tracking info
* `POST   /customer/bookings/{id}/accept-quotation` - Approve provider quotation
* `POST   /customer/bookings/{id}/cancel` - Cancel booking with reason
* `POST   /customer/bookings/{id}/review` - Submit rating & multi-attribute review

### C. Service Provider Workflows (`/api/v1/provider`)
* `GET    /provider/profile` - Detailed provider profile & operational status
* `PUT    /provider/status/online` - Toggle online/offline duty status
* `PUT    /provider/location` - Update current GPS coordinates (lat, lon)
* `POST   /provider/documents` - Upload verification documents (CNIC, certificates)
* `GET    /provider/services` - List offered services with pricing status
* `POST   /provider/services/propose-price` - Submit proposed price for admin review
* `GET    /provider/bookings/requests` - Real-time incoming booking leads
* `POST   /provider/bookings/{id}/accept` - Accept incoming job
* `POST   /provider/bookings/{id}/reject` - Decline job request
* `POST   /provider/bookings/{id}/quotation` - Submit customized quotation & scope
* `POST   /provider/bookings/{id}/start-travel` - Transition to 'Provider On The Way'
* `POST   /provider/bookings/{id}/start-job` - Verify OTP and mark 'In Progress'
* `POST   /provider/bookings/{id}/complete` - Upload after-photos and mark 'Completed'
* `GET    /provider/wallet` - Ledger balance, pending payouts, commission deductions
* `POST   /provider/wallet/withdraw` - Request bank settlement payout

### D. Admin Management Portal (`/api/v1/admin`)
* `GET    /admin/dashboard/metrics` - Platform operational & financial KPIs
* `GET    /admin/providers/verifications` - Pending provider KYC documents
* `POST   /admin/providers/{id}/verify` - Approve / Reject provider profile
* `GET    /admin/pricing/pending` - Pending provider service price submissions
* `POST   /admin/pricing/{id}/approve` - Approve or adjust service price
* `GET    /admin/services` - Manage master services, categories, and market rate limits
* `POST   /admin/services` - Create/edit service with ceiling & floor rates
* `GET    /admin/bookings` - Global booking monitor with spatial filtering
* `GET    /admin/commissions/rules` - View/configure commission percentages
* `POST   /admin/commissions/rules` - Create service-specific commission policy
* `GET    /admin/settlements` - Provider payout withdrawal queue
* `POST   /admin/settlements/{id}/process` - Mark bank transfer as completed
* `GET    /admin/disputes` - Arbitrate complaints and customer disputes
* `GET    /admin/audit-logs` - Immutable audit log of administrative actions
