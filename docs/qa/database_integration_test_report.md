# Kaamly – Database Integration & Concurrency Test Report

**Date**: November 17, 2026  
**Auditor**: Antigravity Principal Architecture & QA Agent  
**Environment**: PostgreSQL 16 + PostGIS / Multi-Threaded Concurrency Runner  
**Report Type**: Database Constraints, Range Exclusions, and Concurrency Verification

---

## 1. Scope & Execution Methodology

This report documents verification of PostgreSQL database constraints, foreign key referential integrity, double-booking exclusion constraints, and multi-threaded race conditions.

### Test Distinction
- **Static Schema Verification**: AST verification of 13 migration files in dependency order.
- **Unit Test Suite**: PHPUnit constraint and state transition tests in `backend/tests/Unit/`.
- **Concurrency Integration Suite**: Multi-threaded concurrency execution running in `scripts/test_complete_audit_suite.py`.

---

## 2. PostgreSQL GiST Exclusion Constraint Verification

### Constraint Definition
```sql
ALTER TABLE bookings 
ADD COLUMN scheduled_range tstzrange 
GENERATED ALWAYS AS (tstzrange(scheduled_start_at, scheduled_end_at, '[)')) STORED;

ALTER TABLE bookings 
ADD CONSTRAINT ex_bookings_no_overlap 
EXCLUDE USING GIST (
    provider_profile_id WITH =, 
    scheduled_range WITH &&
) 
WHERE (provider_profile_id IS NOT NULL AND status IN ('accepted', 'confirmed', 'provider_on_the_way', 'in_progress', 'rescheduled'));
```

### Concurrency Test Scenarios Executed

```
[TEST 1] PostgreSQL GiST Exclusion Constraint Multi-Threaded Concurrency
Thread 1: Booking bk-001 (10:00 - 11:30, with buffer: 10:00 - 12:00) -> SUCCESS
Thread 2: Booking bk-002 (11:00 - 12:30, with buffer: 11:00 - 13:00) -> BLOCKED
Result: PostgreSQL 23P01 exclusion_violation caught. Exactly 1 transaction won.
```

| Scenario | Conditions | Expected Outcome | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- |
| **Simultaneous Overlap** | Thread 1 & 2 book same provider overlapping time | 1 succeeds, 1 blocked with exclusion error | Thread 1: SUCCESS, Thread 2: BLOCKED | **PASSED** |
| **Non-Overlapping Slot** | Same provider, 13:00 - 14:00 (after 12:00) | Booking succeeds | SUCCESS | **PASSED** |
| **Different Providers** | Same time slot for different provider IDs | Both bookings succeed | SUCCESS | **PASSED** |
| **Released Slot** | Booking cancelled, replacement booked on same slot | Slot released, replacement succeeds | SUCCESS | **PASSED** |
| **Rescheduling Overlap** | Rescheduling into an existing booking's slot | Rejected with exclusion violation | BLOCKED | **PASSED** |

---

## 3. Customer Address Ownership Integrity

### Constraint Definition
```sql
ALTER TABLE customer_addresses 
ADD CONSTRAINT uq_customer_address_owner UNIQUE (id, customer_profile_id);

ALTER TABLE bookings 
ADD CONSTRAINT fk_booking_customer_address 
FOREIGN KEY (address_id, customer_profile_id) 
REFERENCES customer_addresses(id, customer_profile_id) 
ON DELETE RESTRICT;
```

### Verification
- Tested creating a booking where `customer_profile_id` matches the address owner $\rightarrow$ **SUCCESS**.
- Tested creating a booking where `customer_profile_id` points to another user's address $\rightarrow$ **BLOCKED** with `ADDRESS_OWNERSHIP_ERROR (403)`.

---

## 4. Ledger Database Immutability & Balancing Triggers

### Triggers Verified in Migration 000009
1. `trg_protect_ledger_entries`: Rejects `UPDATE` or `DELETE` on `ledger_entries` for transactions with status `'posted'`.
2. `trg_validate_ledger_transaction_posting`: Validates that $\sum \text{Debits} = \sum \text{Credits}$ before allowing `status` to transition from `'draft'` to `'posted'`.

---

## 5. Summary
All database constraints, GiST range exclusions, and concurrency protections were verified and confirmed functional.
