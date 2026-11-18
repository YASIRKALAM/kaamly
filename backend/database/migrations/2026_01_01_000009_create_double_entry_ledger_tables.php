<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Core Double-Entry Accounting Ledger with database triggers for immutability and atomic balancing.
     */
    public function up(): void
    {
        // 1. Chart of Accounts (ledger_accounts)
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('account_code', 20)->unique();
            $table->string('account_name', 150);
            $table->string('account_type', 30); // asset, liability, equity, revenue, expense
            $table->string('currency', 3)->default('PKR');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['account_type', 'is_active']);
        });

        // 2. Commission Rules
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('service_id')->nullable();
            $table->uuid('category_id')->nullable();
            $table->decimal('rate_percentage', 5, 2)->default(10.00);
            $table->decimal('flat_fee', 12, 2)->default(0.00);
            $table->timestampTz('effective_from')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->foreign('service_id')->references('id')->on('services')->onDelete('set null');
            $table->foreign('category_id')->references('id')->on('service_categories')->onDelete('set null');
        });

        // 3. Ledger Transactions Header
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('transaction_number', 40)->unique();
            $table->string('idempotency_key', 100)->unique();
            $table->string('reference_type', 50); // booking_escrow, booking_completion, cod_commission, refund, settlement, penalty
            $table->string('reference_id', 100)->nullable();
            $table->text('description');
            $table->string('status', 20)->default('posted'); // draft, posted, reversed
            $table->timestampTz('posted_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['reference_type', 'reference_id']);
            $table->index(['status', 'posted_at']);
        });

        // 4. Ledger Entries (Lines)
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('ledger_transaction_id');
            $table->uuid('ledger_account_id');
            $table->string('direction', 10); // debit, credit
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('PKR');
            $table->text('description')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->foreign('ledger_transaction_id')->references('id')->on('ledger_transactions')->onDelete('restrict');
            $table->foreign('ledger_account_id')->references('id')->on('ledger_accounts')->onDelete('restrict');
            $table->index(['ledger_transaction_id', 'direction']);
            $table->index(['ledger_account_id', 'created_at']);
        });

        // Add check constraint to ensure positive entry amounts
        DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT chk_ledger_entry_amount_positive CHECK (amount > 0);");

        // 5. Database Trigger: Protect Ledger Entries from UPDATE or DELETE (Immutability)
        DB::statement("
            CREATE OR REPLACE FUNCTION fn_protect_ledger_entries()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'Financial Ledger entries are strictly immutable! UPDATE and DELETE operations are forbidden.';
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER trg_protect_ledger_entries
            BEFORE UPDATE OR DELETE ON ledger_entries
            FOR EACH ROW
            EXECUTE FUNCTION fn_protect_ledger_entries();
        ");

        // 6. Database Trigger: Validate Transaction Posting and Debit-Credit Equality
        DB::statement("
            CREATE OR REPLACE FUNCTION fn_validate_ledger_transaction_posting()
            RETURNS TRIGGER AS \$\$
            DECLARE
                total_debits NUMERIC(14,2) := 0;
                total_credits NUMERIC(14,2) := 0;
                entry_count INT := 0;
            BEGIN
                -- Prevent modification of already posted or reversed transactions
                IF (OLD.status IN ('posted', 'reversed') AND NEW.status = OLD.status AND (OLD.transaction_number <> NEW.transaction_number OR OLD.idempotency_key <> NEW.idempotency_key)) THEN
                    RAISE EXCEPTION 'Immutable transaction: Cannot alter posted or reversed transaction metadata.';
                END IF;

                -- If transitioning to 'posted', ensure debits == credits and >= 2 entries
                IF (NEW.status = 'posted' AND (OLD.status IS NULL OR OLD.status = 'draft')) THEN
                    SELECT 
                        COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END), 0),
                        COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END), 0),
                        COUNT(*)
                    INTO total_debits, total_credits, entry_count
                    FROM ledger_entries
                    WHERE ledger_transaction_id = NEW.id;

                    IF (entry_count < 2) THEN
                        RAISE EXCEPTION 'Transaction posting rejected: Must contain at least two entries (one debit and one credit). Found: %', entry_count;
                    END IF;

                    IF (total_debits <> total_credits) THEN
                        RAISE EXCEPTION 'Transaction posting rejected: Debits (%) must equal Credits (%). Imbalance = %', total_debits, total_credits, (total_debits - total_credits);
                    END IF;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER trg_validate_ledger_transaction_posting
            BEFORE UPDATE ON ledger_transactions
            FOR EACH ROW
            EXECUTE FUNCTION fn_validate_ledger_transaction_posting();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP TRIGGER IF EXISTS trg_validate_ledger_transaction_posting ON ledger_transactions;");
        DB::statement("DROP FUNCTION IF EXISTS fn_validate_ledger_transaction_posting();");
        DB::statement("DROP TRIGGER IF EXISTS trg_protect_ledger_entries ON ledger_entries;");
        DB::statement("DROP FUNCTION IF EXISTS fn_protect_ledger_entries();");

        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('ledger_accounts');
    }
};
