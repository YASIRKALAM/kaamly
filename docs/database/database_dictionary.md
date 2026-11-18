# KAAMLY PLATFORM – REVISED & HARDENED DATABASE DICTIONARY

This data dictionary reflects the production database schema of the **Kaamly** PostgreSQL 16+ database, incorporating PostGIS spatial models, the `btree_gist` range exclusion engine, and the immutable Double-Entry General Ledger.

---

## Domain A: Identity, Authentication & Access Control (RBAC)

### Table: `users`
Central identity record for all individuals using the platform.

| Column | Type | Nullable | Default | Description |
| :--- | :--- | :---: | :---: | :--- |
| `id` | `UUID` | No | `gen_random_uuid()` | Immutable primary key. |
| `phone_number` | `VARCHAR(20)` | No | - | E.164 normalized mobile number (`UNIQUE`). |
| `email` | `VARCHAR(255)` | Yes | - | User email address (`UNIQUE`). |
| `password_hash` | `VARCHAR(255)` | Yes | - | Bcrypt / Argon2id password hash. |
| `first_name` | `VARCHAR(100)` | No | - | User's first name. |
| `last_name` | `VARCHAR(100)` | No | - | User's last name. |
| `user_type` | `user_type_enum`| No | `'customer'` | `customer`, `provider`, `admin`, `moderator`, `support`. |
| `status` | `user_status_enum`| No | `'pending_verification'` | `active`, `suspended`, `pending_verification`, `deactivated`. |
| `avatar_url` | `TEXT` | Yes | - | Cloud storage path to avatar image. |
| `phone_verified_at` | `TIMESTAMPTZ` | Yes | - | Verification timestamp for SMS OTP. |
| `email_verified_at` | `TIMESTAMPTZ` | Yes | - | Verification timestamp for email link. |
| `last_login_at` | `TIMESTAMPTZ` | Yes | - | Most recent login session timestamp. |
| `last_login_ip` | `INET` | Yes | - | IP address of last login. |
| `fcm_token` | `TEXT` | Yes | - | Firebase Cloud Messaging device registration token. |
| `created_at` | `TIMESTAMPTZ` | No | `CURRENT_TIMESTAMP`| Audit creation timestamp. |
| `updated_at` | `TIMESTAMPTZ` | No | `CURRENT_TIMESTAMP`| Audit modification timestamp. |
| `deleted_at` | `TIMESTAMPTZ` | Yes | - | Soft delete flag. |

---

## Domain B: Customer Profiles & Multi-Address Spatial Store

### Table: `customer_profiles`
Customer-specific preferences, spending aggregates, and platform ratings.

| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | `PRIMARY KEY` | Profile identifier. |
| `user_id` | `UUID` | `UNIQUE`, `FK -> users(id)` | Linked user account. |
| `default_address_id`| `UUID` | `FK -> customer_addresses(id)` | Current default delivery address. |
| `gender` | `VARCHAR(20)` | `NULLABLE` | Gender preference for gender-restricted services. |
| `date_of_birth` | `DATE` | `NULLABLE` | Age verification. |
| `emergency_contact_name`| `VARCHAR(100)` | `NULLABLE` | Emergency contact person name. |
| `emergency_contact_phone`| `VARCHAR(20)` | `NULLABLE` | Application encrypted phone number. |
| `total_bookings` | `INT` | `NOT NULL`, default `0` | Completed bookings count. |
| `total_spent` | `NUMERIC(14,2)`| `NOT NULL`, default `0.00`| Cumulative spend in PKR. |
| `rating_as_customer` | `NUMERIC(3,2)`| `NOT NULL`, default `5.00`| Rating given to customer by providers. |

### Table: `customer_addresses`
Customer addresses with native PostGIS spatial points and composite ownership integrity.

| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | `PRIMARY KEY` | Address identifier. |
| `customer_profile_id`| `UUID` | `NOT NULL`, `FK -> customer_profiles(id)` | Owner profile. |
| `label` | `VARCHAR(50)` | `NOT NULL` | 'Home', 'Office', 'Other', etc. |
| `street_address` | `TEXT` | `NOT NULL` | Physical street address. |
| `area_neighborhood` | `VARCHAR(150)`| `NOT NULL` | Colony, Phase or Neighborhood. |
| `city` | `VARCHAR(100)`| `NOT NULL` | City name. |
| `province_state` | `VARCHAR(100)`| `NOT NULL` | Province / State. |
| `postal_code` | `VARCHAR(20)` | `NULLABLE` | Postal code. |
| `location` | `GEOGRAPHY(Point,4326)` | `NOT NULL` | WGS 84 spatial point coordinate. |
| `latitude` | `NUMERIC(10,8)`| `NOT NULL` | Decimal latitude. |
| `longitude` | `NUMERIC(11,8)`| `NOT NULL` | Decimal longitude. |
| `is_default` | `BOOLEAN` | `NOT NULL`, default `FALSE` | Primary address selection flag. |
| **Constraint** | `uq_customer_address_owner` | `UNIQUE (id, customer_profile_id)` | Enforces address ownership integrity in bookings. |

---

## Domain C: Service Providers, KYC & Verification

### Table: `provider_profiles`
Operational parameters, ratings, real-time location, and verification status.

| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | `PRIMARY KEY` | Provider profile identifier. |
| `user_id` | `UUID` | `UNIQUE`, `FK -> users(id)` | Associated user account. |
| `business_name` | `VARCHAR(150)`| `NULLABLE` | Operating business name. |
| `experience_years` | `INT` | `NOT NULL`, default `1` | Professional experience. |
| `verification_status`| `enum` | `NOT NULL`, default `'draft'` | `draft`, `submitted`, `under_review`, `verified`, `rejected`, `suspended`. |
| `is_online` | `BOOLEAN` | `NOT NULL`, default `FALSE` | Real-time on-duty switch. |
| `current_location` | `GEOGRAPHY(Point,4326)` | `NULLABLE` | Real-time GPS location from mobile device. |
| `location_updated_at`| `TIMESTAMPTZ` | `NULLABLE` | Freshness timestamp of last GPS coordinate report. |
| `service_radius_km` | `NUMERIC(5,2)`| `NOT NULL`, default `10.00` | Maximum travel distance for dispatch (1-50 km). |
| `rating_average` | `NUMERIC(3,2)`| `NOT NULL`, default `0.00` | Calculated aggregate score (0.00 to 5.00). |
| `rating_count` | `INT` | `NOT NULL`, default `0` | Number of verified customer reviews. |
| `completed_jobs_count`| `INT` | `NOT NULL`, default `0` | Completed jobs count. |
| `acceptance_rate` | `NUMERIC(5,2)`| `NOT NULL`, default `100.00`| Percentage of broadcast leads accepted. |
| `cancellation_rate`| `NUMERIC(5,2)`| `NOT NULL`, default `0.00` | Percentage of accepted jobs cancelled. |

---

## Domain F: Bookings & Workflow with Overlap Exclusion

### Table: `bookings`
Core operational transaction connecting customer, provider, and work delivery.

| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | `PRIMARY KEY` | Unique booking ID. |
| `booking_number` | `VARCHAR(30)` | `UNIQUE`, `NOT NULL` | Human-friendly reference (`KML-YYYYMM-XXXXX`). |
| `customer_profile_id`| `UUID` | `NOT NULL`, `FK -> customer_profiles(id)` | Customer reference. |
| `provider_profile_id`| `UUID` | `NOT NULL`, `FK -> provider_profiles(id)` | Assigned provider. |
| `service_id` | `UUID` | `NOT NULL`, `FK -> services(id)` | Master service item. |
| `address_id` | `UUID` | `NOT NULL` | Work site address. |
| `status` | `enum` | `NOT NULL`, default `'pending'` | 13-state workflow machine. |
| `payment_method` | `enum` | `NOT NULL`, default `'cash_on_delivery'` | `cash_on_delivery`, `wallet`, `credit_card`. |
| `payment_status` | `enum` | `NOT NULL`, default `'pending'` | `pending`, `escrow_held`, `captured`, `settled`. |
| `scheduled_start_at`| `TIMESTAMPTZ` | `NOT NULL` | Appointment start timestamp. |
| `scheduled_end_at` | `TIMESTAMPTZ` | `NOT NULL` | End timestamp (includes 30-min travel buffer). |
| `scheduled_range` | `TSTZRANGE` | `GENERATED ALWAYS AS (tstzrange(...))` | Range column for GiST exclusion constraints. |
| `scheduled_date` | `DATE` | `NOT NULL` | Appointment calendar date. |
| `scheduled_time_slot`| `VARCHAR(50)` | `NOT NULL` | Canonical slot label (e.g. `'14:00 - 16:00'`). |
| `currency` | `VARCHAR(10)` | `NOT NULL`, default `'PKR'` | Currency code. |
| `agreed_base_price` | `NUMERIC(12,2)`| `NOT NULL` | Base labor charge (Snapshot). |
| `materials_cost` | `NUMERIC(12,2)`| `NOT NULL`, default `0.00` | Replaced parts cost (Snapshot). |
| `customer_fee` | `NUMERIC(12,2)`| `NOT NULL`, default `0.00` | Booking service fee (Snapshot). |
| `total_amount` | `NUMERIC(12,2)`| `NOT NULL` | Grand total payable (Snapshot). |
| `provider_earning` | `NUMERIC(12,2)`| `NOT NULL` | Net provider earning (Snapshot). |
| `platform_commission`| `NUMERIC(12,2)`| `NOT NULL` | Platform fee revenue (Snapshot). |
| `start_otp_code` | `VARCHAR(6)` | `NULLABLE` | 6-digit PIN required to start job. |
| `service_location` | `GEOGRAPHY(Point,4326)` | `NOT NULL` | PostGIS spatial point for geofence check. |
| **Constraint** | `fk_booking_customer_address` | `FK (address_id, customer_profile_id)` | Enforces address ownership integrity. |
| **Constraint** | `ex_bookings_no_overlap` | `EXCLUDE USING GIST (...)` | Range overlap exclusion: blocks double-booking. |

---

## Domain H: Double-Entry Financial Ledger & Settlements

### Table: `ledger_accounts`
Chart of accounts for balanced double-entry accounting.

| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | `PRIMARY KEY` | Account identifier. |
| `account_number` | `VARCHAR(30)` | `UNIQUE`, `NOT NULL` | Account code (`1010`, `1020`, `1030-UUID`, `2010`, `2020-UUID`, `4010`). |
| `name` | `VARCHAR(150)`| `NOT NULL` | Human-readable account title. |
| `account_type` | `enum` | `NOT NULL` | `asset`, `liability`, `equity`, `revenue`, `expense`. |
| `currency` | `VARCHAR(10)` | `NOT NULL`, default `'PKR'` | Standard currency code. |
| `user_id` | `UUID` | `FK -> users(id)` | User ID (populated for provider receivable/payable and customer wallet; NULL for system accounts). |
| `is_system` | `BOOLEAN` | `NOT NULL`, default `FALSE` | Permanent system account flag. |

### Table: `ledger_transactions`
Header transaction record enforcing balance immutability and idempotency.

| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | `PRIMARY KEY` | Transaction identifier. |
| `transaction_number` | `VARCHAR(50)` | `UNIQUE`, `NOT NULL` | Reference (`TXN-YYYYMM-XXXXX`). |
| `booking_id` | `UUID` | `FK -> bookings(id)` | Associated booking. |
| `reference_type` | `VARCHAR(50)` | `NOT NULL` | `booking_payment`, `cod_commission_charge`, `settlement_payout`, `refund`, `reversal`. |
| `status` | `enum` | `NOT NULL`, default `'draft'`| `draft`, `posted`, `voided`. |
| `currency` | `VARCHAR(10)` | `NOT NULL`, default `'PKR'` | Currency code. |
| `idempotency_key` | `VARCHAR(100)`| `UNIQUE`, `NOT NULL` | Client request key preventing duplicate postings. |
| `posted_at` | `TIMESTAMPTZ` | `NULLABLE` | Timestamp when entry became immutable. |

### Table: `ledger_entries`
Individual debit and credit postings. Protected by trigger `trg_protect_ledger_entries`.

| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | `PRIMARY KEY` | Entry ID. |
| `transaction_id` | `UUID` | `NOT NULL`, `FK -> ledger_transactions(id)` | Parent balanced transaction. |
| `account_id` | `UUID` | `NOT NULL`, `FK -> ledger_accounts(id)` | Target chart account. |
| `currency` | `VARCHAR(10)` | `NOT NULL`, default `'PKR'` | Currency code. |
| `entry_type` | `VARCHAR(10)` | `NOT NULL`, check `'debit'` or `'credit'` | Debit or Credit. |
| `amount` | `NUMERIC(14,2)`| `NOT NULL`, check `amount > 0` | Monetary amount in PKR. |
| `memo` | `TEXT` | `NULLABLE` | Specific ledger line memo. |
