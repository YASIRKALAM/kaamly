-- ==============================================================================
-- KAAMLY PLATFORM - MASTER PRODUCTION POSTGRESQL SCHEMA (REVISED & HARDENED)
-- PostgreSQL 16+ with PostGIS, btree_gist, Range Exclusions & Immutable Ledger
-- ==============================================================================

-- 1. EXTENSIONS & PREREQUISITES
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "postgis";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "btree_gist"; -- Required for GiST multi-column exclusion constraints

-- ==============================================================================
-- DOMAIN A: IDENTITY, AUTHENTICATION & ACCESS CONTROL (RBAC)
-- ==============================================================================

CREATE TYPE user_status_enum AS ENUM ('active', 'suspended', 'pending_verification', 'deactivated');
CREATE TYPE user_type_enum AS ENUM ('customer', 'provider', 'admin', 'moderator', 'support');

CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    phone_number VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE,
    password_hash VARCHAR(255),
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    user_type user_type_enum NOT NULL DEFAULT 'customer',
    status user_status_enum NOT NULL DEFAULT 'pending_verification',
    avatar_url TEXT,
    phone_verified_at TIMESTAMP WITH TIME ZONE,
    email_verified_at TIMESTAMP WITH TIME ZONE,
    last_login_at TIMESTAMP WITH TIME ZONE,
    last_login_ip INET,
    fcm_token TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at TIMESTAMP WITH TIME ZONE
);

CREATE TABLE roles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(50) UNIQUE NOT NULL,
    guard_name VARCHAR(50) DEFAULT 'api' NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) UNIQUE NOT NULL,
    guard_name VARCHAR(50) DEFAULT 'api' NOT NULL,
    group_name VARCHAR(50) NOT NULL,
    display_name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE user_roles (
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role_id UUID NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY (user_id, role_id)
);

CREATE TABLE role_permissions (
    role_id UUID NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id UUID NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE otp_verifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    phone_number VARCHAR(20) NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    purpose VARCHAR(30) NOT NULL, -- 'registration', 'login', 'reset_password'
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE NOT NULL,
    attempt_count INT DEFAULT 0 NOT NULL,
    ip_address INET,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE login_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    device_id VARCHAR(100),
    device_model VARCHAR(100),
    os_name VARCHAR(50),
    os_version VARCHAR(50),
    app_version VARCHAR(30),
    ip_address INET,
    user_agent TEXT,
    token_id VARCHAR(255) NOT NULL,
    is_revoked BOOLEAN DEFAULT FALSE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_activity_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- ==============================================================================
-- DOMAIN B: CUSTOMERS
-- ==============================================================================

CREATE TABLE customer_profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    default_address_id UUID,
    gender VARCHAR(20),
    date_of_birth DATE,
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    preferred_language VARCHAR(10) DEFAULT 'en',
    total_bookings INT DEFAULT 0 NOT NULL,
    total_spent NUMERIC(14, 2) DEFAULT 0.00 NOT NULL,
    rating_as_customer NUMERIC(3, 2) DEFAULT 5.00 NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE customer_addresses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_profile_id UUID NOT NULL REFERENCES customer_profiles(id) ON DELETE CASCADE,
    label VARCHAR(50) NOT NULL, -- 'Home', 'Office', 'Other'
    street_address TEXT NOT NULL,
    apartment_unit VARCHAR(100),
    area_neighborhood VARCHAR(150) NOT NULL,
    city VARCHAR(100) NOT NULL,
    province_state VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20),
    location GEOGRAPHY(Point, 4326) NOT NULL,
    latitude NUMERIC(10, 8) NOT NULL,
    longitude NUMERIC(11, 8) NOT NULL,
    is_default BOOLEAN DEFAULT FALSE NOT NULL,
    instructions_for_provider TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    -- Composite unique key to enforce address ownership integrity in bookings
    CONSTRAINT uq_customer_address_owner UNIQUE (id, customer_profile_id)
);

ALTER TABLE customer_profiles 
ADD CONSTRAINT fk_customer_default_address 
FOREIGN KEY (default_address_id) REFERENCES customer_addresses(id) ON DELETE SET NULL;

CREATE TABLE customer_saved_providers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_profile_id UUID NOT NULL REFERENCES customer_profiles(id) ON DELETE CASCADE,
    provider_id UUID NOT NULL, -- References provider_profiles(id)
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- ==============================================================================
-- DOMAIN C: SERVICE PROVIDERS
-- ==============================================================================

CREATE TYPE provider_verification_status_enum AS ENUM ('draft', 'submitted', 'under_review', 'verified', 'rejected', 'suspended');

CREATE TABLE provider_profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    business_name VARCHAR(150),
    bio TEXT,
    experience_years INT DEFAULT 1 NOT NULL,
    verification_status provider_verification_status_enum NOT NULL DEFAULT 'draft',
    is_online BOOLEAN DEFAULT FALSE NOT NULL,
    is_available_for_urgent BOOLEAN DEFAULT FALSE NOT NULL,
    current_location GEOGRAPHY(Point, 4326),
    location_updated_at TIMESTAMP WITH TIME ZONE,
    service_radius_km NUMERIC(5, 2) DEFAULT 10.00 NOT NULL CHECK (service_radius_km > 0 AND service_radius_km <= 50.00),
    rating_average NUMERIC(3, 2) DEFAULT 0.00 NOT NULL CHECK (rating_average >= 0.00 AND rating_average <= 5.00),
    rating_count INT DEFAULT 0 NOT NULL,
    completed_jobs_count INT DEFAULT 0 NOT NULL,
    cancellation_rate NUMERIC(5, 2) DEFAULT 0.00 NOT NULL,
    acceptance_rate NUMERIC(5, 2) DEFAULT 100.00 NOT NULL,
    verified_at TIMESTAMP WITH TIME ZONE,
    verified_by UUID REFERENCES users(id) ON DELETE SET NULL,
    rejection_reason TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

ALTER TABLE customer_saved_providers
ADD CONSTRAINT fk_saved_provider
FOREIGN KEY (provider_id) REFERENCES provider_profiles(id) ON DELETE CASCADE;

CREATE TABLE provider_documents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider_profile_id UUID NOT NULL REFERENCES provider_profiles(id) ON DELETE CASCADE,
    document_type VARCHAR(50) NOT NULL, -- 'cnic_front', 'cnic_back', 'trade_license', 'police_character_cert', 'certification'
    document_number VARCHAR(100), -- Application encrypted if CNIC
    file_path TEXT NOT NULL, -- S3 private bucket path
    file_mime_type VARCHAR(50) NOT NULL,
    file_size_kb INT,
    status provider_verification_status_enum DEFAULT 'submitted' NOT NULL,
    reviewed_by UUID REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at TIMESTAMP WITH TIME ZONE,
    reviewer_notes TEXT,
    expires_at DATE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE provider_bank_accounts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider_profile_id UUID NOT NULL REFERENCES provider_profiles(id) ON DELETE CASCADE,
    bank_name VARCHAR(100) NOT NULL,
    account_title VARCHAR(150) NOT NULL,
    account_number_encrypted TEXT NOT NULL,
    iban_encrypted TEXT,
    branch_code VARCHAR(20),
    is_primary BOOLEAN DEFAULT TRUE NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE NOT NULL,
    verified_at TIMESTAMP WITH TIME ZONE,
    verified_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE provider_status_history (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider_profile_id UUID NOT NULL REFERENCES provider_profiles(id) ON DELETE CASCADE,
    previous_status provider_verification_status_enum NOT NULL,
    new_status provider_verification_status_enum NOT NULL,
    changed_by UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    reason TEXT NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- ==============================================================================
-- DOMAIN D: SERVICE CATALOG, MARKET RATES & PRICING
-- ==============================================================================

CREATE TYPE pricing_type_enum AS ENUM ('fixed', 'hourly', 'inspection_based', 'quotation_required');
CREATE TYPE price_approval_status_enum AS ENUM ('pending', 'approved', 'rejected', 'modified_by_admin');

CREATE TABLE service_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    parent_id UUID REFERENCES service_categories(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) UNIQUE NOT NULL,
    description TEXT,
    icon_url TEXT,
    banner_url TEXT,
    sort_order INT DEFAULT 0 NOT NULL,
    is_active BOOLEAN DEFAULT TRUE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE services (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    category_id UUID NOT NULL REFERENCES service_categories(id) ON DELETE RESTRICT,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) UNIQUE NOT NULL,
    description TEXT NOT NULL,
    image_url TEXT,
    pricing_type pricing_type_enum DEFAULT 'fixed' NOT NULL,
    currency VARCHAR(10) DEFAULT 'PKR' NOT NULL,
    market_rate_min NUMERIC(12, 2) NOT NULL CHECK (market_rate_min >= 0),
    market_rate_max NUMERIC(12, 2) NOT NULL CHECK (market_rate_max >= market_rate_min),
    market_rate_recommended NUMERIC(12, 2) NOT NULL CHECK (market_rate_recommended BETWEEN market_rate_min AND market_rate_max),
    inspection_fee NUMERIC(12, 2) DEFAULT 0.00 NOT NULL CHECK (inspection_fee >= 0),
    estimated_duration_minutes INT DEFAULT 60 NOT NULL CHECK (estimated_duration_minutes > 0),
    is_active BOOLEAN DEFAULT TRUE NOT NULL,
    requires_materials BOOLEAN DEFAULT FALSE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE provider_services (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider_profile_id UUID NOT NULL REFERENCES provider_profiles(id) ON DELETE CASCADE,
    service_id UUID NOT NULL REFERENCES services(id) ON DELETE RESTRICT,
    is_offered BOOLEAN DEFAULT TRUE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    UNIQUE (provider_profile_id, service_id)
);

CREATE TABLE provider_service_prices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider_service_id UUID NOT NULL REFERENCES provider_services(id) ON DELETE CASCADE,
    currency VARCHAR(10) DEFAULT 'PKR' NOT NULL,
    proposed_price NUMERIC(12, 2) NOT NULL CHECK (proposed_price > 0),
    approved_price NUMERIC(12, 2) CHECK (approved_price IS NULL OR approved_price > 0),
    approval_status price_approval_status_enum DEFAULT 'pending' NOT NULL,
    approved_by UUID REFERENCES users(id) ON DELETE SET NULL,
    approved_at TIMESTAMP WITH TIME ZONE,
    admin_feedback TEXT,
    is_current_active BOOLEAN DEFAULT FALSE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE price_approval_history (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider_service_price_id UUID NOT NULL REFERENCES provider_service_prices(id) ON DELETE CASCADE,
    previous_price NUMERIC(12, 2),
    new_price NUMERIC(12, 2) NOT NULL,
    action price_approval_status_enum NOT NULL,
    action_by UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    remarks TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- ==============================================================================
-- DOMAIN E: AVAILABILITY, SCHEDULES & SPATIAL COVERAGE
-- ==============================================================================

CREATE TABLE provider_working_hours (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider_profile_id UUID NOT NULL REFERENCES provider_profiles(id) ON DELETE CASCADE,
    day_of_week INT NOT NULL CHECK (day_of_week BETWEEN 0 AND 6), -- 0=Sunday, 6=Saturday
    start_time TIME NOT NULL,
    end_time TIME NOT NULL CHECK (end_time > start_time),
    is_active BOOLEAN DEFAULT TRUE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    UNIQUE (provider_profile_id, day_of_week)
);

CREATE TABLE provider_day_off_schedules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider_profile_id UUID NOT NULL REFERENCES provider_profiles(id) ON DELETE CASCADE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL CHECK (end_date >= start_date),
    reason VARCHAR(255),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE provider_availability_exceptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider_profile_id UUID NOT NULL REFERENCES provider_profiles(id) ON DELETE CASCADE,
    exception_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    is_available BOOLEAN DEFAULT FALSE NOT NULL, -- FALSE = Unavailable exception; TRUE = Special working hours
    reason VARCHAR(255),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_exception_times CHECK (
        (is_available = FALSE) OR 
        (is_available = TRUE AND start_time IS NOT NULL AND end_time IS NOT NULL AND end_time > start_time)
    )
);

CREATE TABLE service_areas (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    city_name VARCHAR(100) NOT NULL,
    zone_name VARCHAR(150) NOT NULL,
    boundary_polygon GEOMETRY(Polygon, 4326) NOT NULL,
    center_point GEOGRAPHY(Point, 4326) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE provider_service_zones (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider_profile_id UUID NOT NULL REFERENCES provider_profiles(id) ON DELETE CASCADE,
    service_area_id UUID NOT NULL REFERENCES service_areas(id) ON DELETE CASCADE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    UNIQUE (provider_profile_id, service_area_id)
);

-- ==============================================================================
-- DOMAIN F: BOOKINGS, REQUESTS, QUOTATIONS & WORKFLOW
-- ==============================================================================

CREATE TYPE booking_status_enum AS ENUM (
    'pending',
    'broadcasted',
    'accepted',
    'quotation_submitted',
    'awaiting_customer_confirmation',
    'confirmed',
    'provider_on_the_way',
    'in_progress',
    'completed',
    'cancelled',
    'disputed',
    'rescheduled',
    'no_show'
);

CREATE TYPE payment_method_enum AS ENUM ('cash_on_delivery', 'wallet', 'credit_card', 'bank_transfer');
CREATE TYPE payment_status_enum AS ENUM ('pending', 'escrow_held', 'captured', 'refunded', 'settled', 'failed');

CREATE TABLE bookings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    booking_number VARCHAR(30) UNIQUE NOT NULL, -- e.g. 'KML-YYYYMM-XXXXX'
    customer_profile_id UUID NOT NULL REFERENCES customer_profiles(id) ON DELETE RESTRICT,
    provider_profile_id UUID NOT NULL REFERENCES provider_profiles(id) ON DELETE RESTRICT,
    service_id UUID NOT NULL REFERENCES services(id) ON DELETE RESTRICT,
    address_id UUID NOT NULL,
    
    status booking_status_enum DEFAULT 'pending' NOT NULL,
    payment_method payment_method_enum DEFAULT 'cash_on_delivery' NOT NULL,
    payment_status payment_status_enum DEFAULT 'pending' NOT NULL,
    
    -- Precise appointment scheduling with travel buffer
    scheduled_start_at TIMESTAMP WITH TIME ZONE NOT NULL,
    scheduled_end_at TIMESTAMP WITH TIME ZONE NOT NULL CHECK (scheduled_end_at > scheduled_start_at),
    -- Generated timestamp range for exact GiST exclusion overlap detection
    scheduled_range TSTZRANGE GENERATED ALWAYS AS (tstzrange(scheduled_start_at, scheduled_end_at, '[)')) STORED,
    
    scheduled_date DATE NOT NULL,
    scheduled_time_slot VARCHAR(50) NOT NULL,
    is_urgent BOOLEAN DEFAULT FALSE NOT NULL,
    
    currency VARCHAR(10) DEFAULT 'PKR' NOT NULL,
    
    -- Immutable Agreed-Price Financial Snapshot at Confirmation Time
    agreed_base_price NUMERIC(12, 2) NOT NULL CHECK (agreed_base_price >= 0),
    materials_cost NUMERIC(12, 2) DEFAULT 0.00 NOT NULL CHECK (materials_cost >= 0),
    discount_amount NUMERIC(12, 2) DEFAULT 0.00 NOT NULL CHECK (discount_amount >= 0),
    customer_fee NUMERIC(12, 2) DEFAULT 0.00 NOT NULL CHECK (customer_fee >= 0),
    total_amount NUMERIC(12, 2) NOT NULL CHECK (total_amount >= 0),
    
    provider_earning NUMERIC(12, 2) NOT NULL CHECK (provider_earning >= 0),
    platform_commission NUMERIC(12, 2) NOT NULL CHECK (platform_commission >= 0),
    
    start_otp_code VARCHAR(6), -- 6-digit PIN given by customer to provider to begin job
    is_start_otp_verified BOOLEAN DEFAULT FALSE NOT NULL,
    
    customer_notes TEXT,
    provider_notes TEXT,
    cancellation_reason TEXT,
    cancelled_by UUID REFERENCES users(id) ON DELETE RESTRICT,
    cancelled_at TIMESTAMP WITH TIME ZONE,
    started_at TIMESTAMP WITH TIME ZONE,
    completed_at TIMESTAMP WITH TIME ZONE,
    
    service_location GEOGRAPHY(Point, 4326) NOT NULL,
    
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    
    -- Address Ownership Integrity: Ensures the booked address strictly belongs to this booking's customer
    CONSTRAINT fk_booking_customer_address 
        FOREIGN KEY (address_id, customer_profile_id) 
        REFERENCES customer_addresses(id, customer_profile_id) 
        ON DELETE RESTRICT,
        
    -- Database-level Range Exclusion Constraint for Variable Duration & Slot Double-Booking Prevention:
    -- Prevents overlapping active bookings for the same provider at the database storage engine level
    CONSTRAINT ex_bookings_no_overlap
        EXCLUDE USING GIST (
            provider_profile_id WITH =,
            scheduled_range WITH &&
        )
        WHERE (status IN ('accepted', 'confirmed', 'provider_on_the_way', 'in_progress'))
);

CREATE TABLE booking_requests (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    booking_id UUID NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    provider_profile_id UUID NOT NULL REFERENCES provider_profiles(id) ON DELETE CASCADE,
    status VARCHAR(30) DEFAULT 'broadcasted' NOT NULL, -- 'broadcasted', 'viewed', 'accepted', 'rejected', 'expired'
    response_timeout_at TIMESTAMP WITH TIME ZONE NOT NULL,
    responded_at TIMESTAMP WITH TIME ZONE,
    rejection_reason TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    UNIQUE (booking_id, provider_profile_id)
);

CREATE TABLE booking_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    booking_id UUID NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    item_description VARCHAR(255) NOT NULL,
    quantity NUMERIC(8, 2) DEFAULT 1.00 NOT NULL CHECK (quantity > 0),
    unit_price NUMERIC(12, 2) NOT NULL CHECK (unit_price >= 0),
    subtotal NUMERIC(12, 2) NOT NULL CHECK (subtotal >= 0),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE quotations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    booking_id UUID NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    provider_profile_id UUID NOT NULL REFERENCES provider_profiles(id) ON DELETE RESTRICT,
    version INT DEFAULT 1 NOT NULL,
    estimated_labor_cost NUMERIC(12, 2) NOT NULL CHECK (estimated_labor_cost >= 0),
    estimated_materials_cost NUMERIC(12, 2) DEFAULT 0.00 NOT NULL CHECK (estimated_materials_cost >= 0),
    total_quoted_price NUMERIC(12, 2) NOT NULL CHECK (total_quoted_price >= 0),
    currency VARCHAR(10) DEFAULT 'PKR' NOT NULL,
    scope_of_work TEXT NOT NULL,
    status VARCHAR(30) DEFAULT 'submitted' NOT NULL, -- 'submitted', 'accepted', 'rejected', 'expired'
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    UNIQUE (booking_id, version)
);

CREATE TABLE quotation_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    quotation_id UUID NOT NULL REFERENCES quotations(id) ON DELETE CASCADE,
    item_type VARCHAR(30) NOT NULL, -- 'labor', 'part_replacement', 'consumable', 'inspection'
    description VARCHAR(255) NOT NULL,
    unit_cost NUMERIC(12, 2) NOT NULL CHECK (unit_cost >= 0),
    quantity NUMERIC(8, 2) DEFAULT 1.00 NOT NULL CHECK (quantity > 0),
    subtotal NUMERIC(12, 2) NOT NULL CHECK (subtotal >= 0),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE booking_status_history (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    booking_id UUID NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    from_status booking_status_enum NOT NULL,
    to_status booking_status_enum NOT NULL,
    changed_by UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    comment TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE booking_attachments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    booking_id UUID NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    uploaded_by UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    attachment_stage VARCHAR(30) NOT NULL, -- 'initial_request', 'before_job', 'in_progress', 'after_job', 'dispute'
    file_path TEXT NOT NULL,
    file_type VARCHAR(30) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- ==============================================================================
-- DOMAIN G: REAL-TIME MESSAGING & PARTICIPANTS
-- ==============================================================================

CREATE TABLE conversations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    booking_id UUID REFERENCES bookings(id) ON DELETE SET NULL,
    last_message_text TEXT,
    last_message_at TIMESTAMP WITH TIME ZONE,
    is_closed BOOLEAN DEFAULT FALSE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE conversation_participants (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    conversation_id UUID NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    participant_type VARCHAR(30) NOT NULL, -- 'customer', 'provider', 'admin_support'
    last_read_message_id UUID,
    is_active BOOLEAN DEFAULT TRUE NOT NULL,
    joined_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    UNIQUE (conversation_id, user_id)
);

CREATE TABLE messages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    conversation_id UUID NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    sender_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    message_text TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE NOT NULL,
    read_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

ALTER TABLE conversation_participants
ADD CONSTRAINT fk_participant_last_read
FOREIGN KEY (last_read_message_id) REFERENCES messages(id) ON DELETE SET NULL;

CREATE TABLE message_attachments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    message_id UUID NOT NULL REFERENCES messages(id) ON DELETE CASCADE,
    file_path TEXT NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size_kb INT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- ==============================================================================
-- DOMAIN H: FINANCIAL MANAGEMENT & DOUBLE-ENTRY ACCOUNTING LEDGER
-- ==============================================================================

CREATE TYPE account_type_enum AS ENUM ('asset', 'liability', 'equity', 'revenue', 'expense');
CREATE TYPE ledger_transaction_status_enum AS ENUM ('draft', 'posted', 'voided');

CREATE TABLE ledger_accounts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    account_number VARCHAR(30) UNIQUE NOT NULL, -- e.g. '1010', '1030-UUID', '2010', '2020-UUID', '4010'
    name VARCHAR(150) NOT NULL,
    account_type account_type_enum NOT NULL,
    currency VARCHAR(10) DEFAULT 'PKR' NOT NULL,
    user_id UUID REFERENCES users(id) ON DELETE RESTRICT, -- Associated user for user-scoped accounts (receivable/payable/wallet)
    is_system BOOLEAN DEFAULT FALSE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE commission_rules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    service_id UUID REFERENCES services(id) ON DELETE SET NULL, -- NULL means global default
    category_id UUID REFERENCES service_categories(id) ON DELETE SET NULL,
    commission_percentage NUMERIC(5, 2) NOT NULL CHECK (commission_percentage >= 0 AND commission_percentage <= 100),
    customer_service_fee NUMERIC(12, 2) DEFAULT 0.00 NOT NULL CHECK (customer_service_fee >= 0),
    minimum_commission_amount NUMERIC(12, 2) DEFAULT 50.00 NOT NULL CHECK (minimum_commission_amount >= 0),
    effective_from DATE NOT NULL,
    effective_to DATE,
    is_active BOOLEAN DEFAULT TRUE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE ledger_transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_number VARCHAR(50) UNIQUE NOT NULL, -- e.g. 'TXN-YYYYMM-XXXXX'
    booking_id UUID REFERENCES bookings(id) ON DELETE RESTRICT,
    reference_type VARCHAR(50) NOT NULL, -- 'booking_payment', 'cod_commission_charge', 'settlement_payout', 'refund', 'reversal'
    reference_id UUID,
    status ledger_transaction_status_enum DEFAULT 'draft' NOT NULL,
    currency VARCHAR(10) DEFAULT 'PKR' NOT NULL,
    posted_at TIMESTAMP WITH TIME ZONE,
    idempotency_key VARCHAR(100) UNIQUE NOT NULL,
    description TEXT NOT NULL,
    created_by UUID REFERENCES users(id) ON DELETE RESTRICT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE ledger_entries (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID NOT NULL REFERENCES ledger_transactions(id) ON DELETE RESTRICT,
    account_id UUID NOT NULL REFERENCES ledger_accounts(id) ON DELETE RESTRICT,
    currency VARCHAR(10) DEFAULT 'PKR' NOT NULL,
    entry_type VARCHAR(10) NOT NULL CHECK (entry_type IN ('debit', 'credit')),
    amount NUMERIC(14, 2) NOT NULL CHECK (amount > 0),
    memo TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- ==============================================================================
-- DATABASE TRIGGERS FOR UNCOMPROMISING FINANCIAL LEDGER INTEGRITY
-- ==============================================================================

-- 1. Trigger Function: Protect Ledger Entries from Modification/Deletion once Posted
CREATE OR REPLACE FUNCTION fn_protect_ledger_entries()
RETURNS TRIGGER AS $$
DECLARE
    v_status ledger_transaction_status_enum;
BEGIN
    IF TG_OP = 'INSERT' THEN
        SELECT status INTO v_status FROM ledger_transactions WHERE id = NEW.transaction_id;
        IF v_status = 'posted' THEN
            RAISE EXCEPTION 'Ledger Integrity Violation: Cannot add new entries to an already POSTED transaction (%).', NEW.transaction_id;
        END IF;
        RETURN NEW;
    ELSE
        SELECT status INTO v_status FROM ledger_transactions WHERE id = OLD.transaction_id;
        IF v_status = 'posted' THEN
            RAISE EXCEPTION 'Ledger Integrity Violation: Posted ledger entries are immutable and cannot be updated or deleted.';
        END IF;
        IF TG_OP = 'DELETE' THEN
            RETURN OLD;
        ELSE
            RETURN NEW;
        END IF;
    END IF;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_protect_ledger_entries
BEFORE INSERT OR UPDATE OR DELETE ON ledger_entries
FOR EACH ROW EXECUTE FUNCTION fn_protect_ledger_entries();

-- 2. Trigger Function: Validate Debit=Credit Balance and Minimum Entries Before Posting
CREATE OR REPLACE FUNCTION fn_validate_ledger_transaction_posting()
RETURNS TRIGGER AS $$
DECLARE
    v_debit_sum NUMERIC(14, 2);
    v_credit_sum NUMERIC(14, 2);
    v_entry_count INT;
BEGIN
    -- Prevent mutation or deletion of already posted transactions
    IF TG_OP = 'UPDATE' AND OLD.status = 'posted' AND NEW.status = 'posted' THEN
        IF OLD.transaction_number != NEW.transaction_number OR
           OLD.booking_id IS DISTINCT FROM NEW.booking_id OR
           OLD.currency != NEW.currency OR
           OLD.idempotency_key != NEW.idempotency_key THEN
            RAISE EXCEPTION 'Ledger Integrity Violation: Posted transaction headers cannot be modified.';
        END IF;
    END IF;

    IF TG_OP = 'DELETE' AND OLD.status = 'posted' THEN
        RAISE EXCEPTION 'Ledger Integrity Violation: Posted transactions cannot be deleted. Use reversal transactions.';
    END IF;

    -- Transition from draft -> posted validation
    IF TG_OP = 'UPDATE' AND OLD.status = 'draft' AND NEW.status = 'posted' THEN
        -- Check count of entries >= 2
        SELECT COUNT(*) INTO v_entry_count FROM ledger_entries WHERE transaction_id = NEW.id;
        IF v_entry_count < 2 THEN
            RAISE EXCEPTION 'Ledger Integrity Violation: Transaction % must contain at least 2 entries before posting (found %).', NEW.id, v_entry_count;
        END IF;

        -- Check Debit Sum = Credit Sum
        SELECT 
            COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END), 0),
            COALESCE(SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END), 0)
        INTO v_debit_sum, v_credit_sum
        FROM ledger_entries
        WHERE transaction_id = NEW.id;

        IF v_debit_sum != v_credit_sum THEN
            RAISE EXCEPTION 'Ledger Unbalanced Violation: Transaction % does not balance (Debit: %, Credit: %).', NEW.id, v_debit_sum, v_credit_sum;
        END IF;

        -- Stamp posted_at
        NEW.posted_at = CURRENT_TIMESTAMP;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_validate_ledger_transaction_posting
BEFORE UPDATE OR DELETE ON ledger_transactions
FOR EACH ROW EXECUTE FUNCTION fn_validate_ledger_transaction_posting();

CREATE TABLE payment_attempts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    booking_id UUID NOT NULL REFERENCES bookings(id) ON DELETE RESTRICT,
    payment_method payment_method_enum NOT NULL,
    gateway_name VARCHAR(50) NOT NULL, -- 'cod_cash', 'stripe', 'jazzcash', 'easypaisa', 'in_app_wallet'
    gateway_reference VARCHAR(150),
    amount NUMERIC(14, 2) NOT NULL CHECK (amount > 0),
    currency VARCHAR(10) DEFAULT 'PKR' NOT NULL,
    status VARCHAR(30) DEFAULT 'initiated' NOT NULL, -- 'initiated', 'successful', 'failed', 'refunded'
    idempotency_key VARCHAR(100) UNIQUE NOT NULL,
    error_message TEXT,
    gateway_response JSONB,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE payment_webhook_events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    gateway_name VARCHAR(50) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_id VARCHAR(150) UNIQUE NOT NULL,
    payload JSONB NOT NULL,
    signature VARCHAR(255),
    is_processed BOOLEAN DEFAULT FALSE NOT NULL,
    processed_at TIMESTAMP WITH TIME ZONE,
    processing_error TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE settlements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider_profile_id UUID NOT NULL REFERENCES provider_profiles(id) ON DELETE RESTRICT,
    bank_account_id UUID NOT NULL REFERENCES provider_bank_accounts(id) ON DELETE RESTRICT,
    settlement_reference VARCHAR(100) UNIQUE NOT NULL,
    requested_amount NUMERIC(14, 2) NOT NULL CHECK (requested_amount > 0),
    service_charge NUMERIC(12, 2) DEFAULT 0.00 NOT NULL CHECK (service_charge >= 0),
    net_payout_amount NUMERIC(14, 2) NOT NULL CHECK (net_payout_amount > 0),
    currency VARCHAR(10) DEFAULT 'PKR' NOT NULL,
    status VARCHAR(30) DEFAULT 'pending' NOT NULL, -- 'pending', 'processing', 'completed', 'rejected'
    processed_by UUID REFERENCES users(id) ON DELETE SET NULL,
    processed_at TIMESTAMP WITH TIME ZONE,
    bank_transaction_id VARCHAR(100),
    remarks TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE settlement_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    settlement_id UUID NOT NULL REFERENCES settlements(id) ON DELETE CASCADE,
    booking_id UUID REFERENCES bookings(id) ON DELETE RESTRICT,
    ledger_entry_id UUID NOT NULL REFERENCES ledger_entries(id) ON DELETE RESTRICT,
    allocated_amount NUMERIC(14, 2) NOT NULL CHECK (allocated_amount > 0),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE refunds (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    booking_id UUID NOT NULL REFERENCES bookings(id) ON DELETE RESTRICT,
    customer_profile_id UUID NOT NULL REFERENCES customer_profiles(id) ON DELETE RESTRICT,
    refund_amount NUMERIC(14, 2) NOT NULL CHECK (refund_amount > 0),
    currency VARCHAR(10) DEFAULT 'PKR' NOT NULL,
    refund_reason TEXT NOT NULL,
    status VARCHAR(30) DEFAULT 'requested' NOT NULL, -- 'requested', 'approved', 'declined', 'processed'
    approved_by UUID REFERENCES users(id) ON DELETE SET NULL,
    approved_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- ==============================================================================
-- DOMAIN I: REVIEWS, RATINGS, COMPLAINTS & FORMAL DISPUTES
-- ==============================================================================

CREATE TABLE reviews (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    booking_id UUID UNIQUE NOT NULL REFERENCES bookings(id) ON DELETE RESTRICT,
    customer_profile_id UUID NOT NULL REFERENCES customer_profiles(id) ON DELETE RESTRICT,
    provider_profile_id UUID NOT NULL REFERENCES provider_profiles(id) ON DELETE RESTRICT,
    rating_overall NUMERIC(2, 1) NOT NULL CHECK (rating_overall BETWEEN 1.0 AND 5.0),
    rating_punctuality NUMERIC(2, 1) CHECK (rating_punctuality BETWEEN 1.0 AND 5.0),
    rating_quality NUMERIC(2, 1) CHECK (rating_quality BETWEEN 1.0 AND 5.0),
    rating_behavior NUMERIC(2, 1) CHECK (rating_behavior BETWEEN 1.0 AND 5.0),
    review_title VARCHAR(150),
    review_comment TEXT,
    provider_reply TEXT,
    provider_replied_at TIMESTAMP WITH TIME ZONE,
    is_flagged BOOLEAN DEFAULT FALSE NOT NULL,
    is_published BOOLEAN DEFAULT TRUE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TYPE dispute_status_enum AS ENUM (
    'submitted',
    'under_review',
    'evidence_requested',
    'arbitrated_customer_favored',
    'arbitrated_provider_favored',
    'resolved_mutual_agreement',
    'dismissed'
);

CREATE TABLE complaints (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_number VARCHAR(30) UNIQUE NOT NULL,
    booking_id UUID NOT NULL REFERENCES bookings(id) ON DELETE RESTRICT,
    complainant_user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    accused_user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    category VARCHAR(50) NOT NULL, -- 'unprofessional', 'property_damage', 'overcharging', 'no_show'
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    status VARCHAR(30) DEFAULT 'open' NOT NULL,
    assigned_admin_id UUID REFERENCES users(id) ON DELETE SET NULL,
    resolution_notes TEXT,
    resolved_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE disputes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    dispute_number VARCHAR(30) UNIQUE NOT NULL,
    booking_id UUID UNIQUE NOT NULL REFERENCES bookings(id) ON DELETE RESTRICT,
    raised_by_user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    against_user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    dispute_reason VARCHAR(100) NOT NULL, -- 'poor_quality_workmanship', 'property_damage', 'unauthorized_fee', 'incomplete_scope'
    claimed_amount NUMERIC(12, 2) NOT NULL CHECK (claimed_amount >= 0),
    awarded_refund_amount NUMERIC(12, 2) DEFAULT 0.00 NOT NULL CHECK (awarded_refund_amount >= 0),
    awarded_provider_payout NUMERIC(12, 2) DEFAULT 0.00 NOT NULL CHECK (awarded_provider_payout >= 0),
    currency VARCHAR(10) DEFAULT 'PKR' NOT NULL,
    status dispute_status_enum DEFAULT 'submitted' NOT NULL,
    arbitrator_user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    verdict_summary TEXT,
    arbitrated_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- ==============================================================================
-- DOMAIN J: NOTIFICATIONS & PREFERENCES
-- ==============================================================================

CREATE TABLE notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(150) NOT NULL,
    body TEXT NOT NULL,
    notification_type VARCHAR(50) NOT NULL, -- 'booking_update', 'quotation', 'chat', 'payment', 'system'
    action_deep_link TEXT,
    is_read BOOLEAN DEFAULT FALSE NOT NULL,
    read_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE notification_preferences (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    push_enabled BOOLEAN DEFAULT TRUE NOT NULL,
    sms_enabled BOOLEAN DEFAULT TRUE NOT NULL,
    email_enabled BOOLEAN DEFAULT TRUE NOT NULL,
    booking_updates_enabled BOOLEAN DEFAULT TRUE NOT NULL,
    promotional_enabled BOOLEAN DEFAULT FALSE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- ==============================================================================
-- DOMAIN K: SYSTEM CONFIGURATION & AUDIT LOGS
-- ==============================================================================

CREATE TABLE system_configurations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT NOT NULL,
    value_type VARCHAR(30) DEFAULT 'string' NOT NULL, -- 'string', 'json', 'boolean', 'numeric'
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE NOT NULL,
    updated_by UUID REFERENCES users(id) ON DELETE SET NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE audit_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL, -- e.g. 'provider.verified', 'price.approved', 'ledger.adjusted'
    auditable_type VARCHAR(100) NOT NULL,
    auditable_id UUID NOT NULL,
    old_values JSONB,
    new_values JSONB,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- ==============================================================================
-- INDEXES & PERFORMANCE OPTIMIZATIONS
-- ==============================================================================

-- Spatial Indexes (PostGIS GIST)
CREATE INDEX idx_provider_profiles_location ON provider_profiles USING GIST (current_location);
CREATE INDEX idx_customer_addresses_location ON customer_addresses USING GIST (location);
CREATE INDEX idx_bookings_location ON bookings USING GIST (service_location);
CREATE INDEX idx_service_areas_boundary ON service_areas USING GIST (boundary_polygon);

-- Core Lookups & Integrity Indexes
CREATE INDEX idx_users_phone ON users(phone_number);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_type_status ON users(user_type, status);

CREATE INDEX idx_provider_profiles_user ON provider_profiles(user_id);
CREATE INDEX idx_provider_profiles_discovery ON provider_profiles(verification_status, is_online, location_updated_at);

CREATE INDEX idx_bookings_customer ON bookings(customer_profile_id);
CREATE INDEX idx_bookings_provider ON bookings(provider_profile_id);
CREATE INDEX idx_bookings_status ON bookings(status);
CREATE INDEX idx_bookings_date_slot ON bookings(scheduled_date, scheduled_time_slot);
CREATE INDEX idx_bookings_number ON bookings(booking_number);

CREATE INDEX idx_messages_conversation ON messages(conversation_id, created_at DESC);
CREATE INDEX idx_conversation_participants ON conversation_participants(conversation_id, user_id);

-- Double-Entry Ledger Performance Indexes
CREATE INDEX idx_ledger_entries_account ON ledger_entries(account_id, created_at DESC);
CREATE INDEX idx_ledger_entries_transaction ON ledger_entries(transaction_id);
CREATE INDEX idx_ledger_tx_booking ON ledger_transactions(booking_id);
CREATE INDEX idx_ledger_tx_idempotency ON ledger_transactions(idempotency_key);
CREATE INDEX idx_ledger_accounts_user ON ledger_accounts(user_id);

CREATE INDEX idx_payment_attempts_booking ON payment_attempts(booking_id);
CREATE INDEX idx_settlement_items_settlement ON settlement_items(settlement_id);
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read, created_at DESC);
CREATE INDEX idx_audit_logs_type_id ON audit_logs(auditable_type, auditable_id);
