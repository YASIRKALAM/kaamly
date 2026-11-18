<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enables required PostgreSQL extensions and creates custom ENUM types.
     */
    public function up(): void
    {
        // 1. Required PostgreSQL Extensions
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto";');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "postgis";');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "btree_gist";');

        // 2. Custom ENUM Types
        DB::statement("DO $$ BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_type_enum') THEN
                CREATE TYPE user_type_enum AS ENUM ('customer', 'provider', 'admin', 'system');
            END IF;
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_status_enum') THEN
                CREATE TYPE user_status_enum AS ENUM ('active', 'suspended', 'banned', 'pending_verification', 'deleted');
            END IF;
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'provider_status_enum') THEN
                CREATE TYPE provider_status_enum AS ENUM ('draft', 'pending_approval', 'approved', 'rejected', 'suspended');
            END IF;
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'pricing_model_enum') THEN
                CREATE TYPE pricing_model_enum AS ENUM ('fixed', 'hourly', 'inspection_quote');
            END IF;
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'booking_status_enum') THEN
                CREATE TYPE booking_status_enum AS ENUM (
                    'pending', 'broadcasted', 'accepted', 'quotation_submitted', 
                    'awaiting_customer_confirmation', 'confirmed', 'provider_on_the_way', 
                    'in_progress', 'completed', 'cancelled', 'disputed', 'rescheduled', 'no_show'
                );
            END IF;
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'payment_mode_enum') THEN
                CREATE TYPE payment_mode_enum AS ENUM ('online_escrow', 'cash_on_delivery', 'wallet');
            END IF;
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'payment_status_enum') THEN
                CREATE TYPE payment_status_enum AS ENUM ('pending', 'escrow_locked', 'released_to_provider', 'refunded', 'failed');
            END IF;
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'ledger_account_type_enum') THEN
                CREATE TYPE ledger_account_type_enum AS ENUM ('asset', 'liability', 'equity', 'revenue', 'expense');
            END IF;
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'ledger_entry_direction_enum') THEN
                CREATE TYPE ledger_entry_direction_enum AS ENUM ('debit', 'credit');
            END IF;
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'ledger_transaction_status_enum') THEN
                CREATE TYPE ledger_transaction_status_enum AS ENUM ('draft', 'posted', 'reversed');
            END IF;
        END $$;");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP TYPE IF EXISTS ledger_transaction_status_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS ledger_entry_direction_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS ledger_account_type_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS payment_status_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS payment_mode_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS booking_status_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS pricing_model_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS provider_status_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS user_status_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS user_type_enum CASCADE;");
    }
};
