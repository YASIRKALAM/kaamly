# Kaamly – Security & Admin Portal Governance Test Report

**Date**: November 17, 2026  
**Auditor**: Antigravity Principal Architecture & QA Agent  
**Environment**: Next.js 16 Web Portal / Laravel 13 RBAC Middleware  
**Report Type**: Admin RBAC, Document Privacy, Maker-Checker Dual Control, and Audit Logging

---

## 1. Role-Based Access Control (RBAC) Matrix

| Portal Module / Endpoint | Route Path | Super Admin | Finance Manager | Operations Manager | Customer / Provider |
| :--- | :--- | :---: | :---: | :---: | :---: |
| **Dashboard KPIs** | `/` | YES | YES | YES | NO (403) |
| **Provider Verification** | `/providers` | YES | READ | YES | NO (403) |
| **Document Decryption** | `/api/v1/providers/{id}/doc` | YES | NO | YES | NO (403) |
| **Live Bookings Monitor** | `/bookings` | YES | READ | YES | NO (403) |
| **Dispute Arbitration** | `/disputes` | YES | NO | YES | NO (403) |
| **Chart of Accounts** | `/finance` | YES | YES | READ | NO (403) |
| **Transaction Reversal** | `/api/v1/admin/ledger/reversal` | YES | YES | NO | NO (403) |
| **Settlement Generation** | `/settlements` | YES | YES | NO | NO (403) |
| **Settlement Payout** | `/api/v1/admin/settlements/{id}/process`| YES | YES | NO | NO (403) |
| **Regulatory Audit Logs**| `/audit` | YES | READ | READ | NO (403) |

---

## 2. Sensitive Data Encryption & Document Storage

### CNIC & Bank Account (IBAN) Protection
- **Storage Encryption**: Handled via PostgreSQL `pgcrypto` and Laravel `Crypt::encryptString()`. CNIC and IBAN fields are never stored in plaintext.
- **Display Masking**: Admin UI renders masked strings (`35201-*****67-1`, `PK36SCBL**********6701`). Full details are unmasked only on audited single-view clicks with role authorization.

### Private Object Storage (MinIO / S3)
- Documents (CNIC scans, police verification certificates, trade licenses) are stored in bucket `kaamly-private-docs` with `private` bucket policy.
- Access is provided exclusively through short-lived presigned URLs (15-minute expiration) generated on-demand by `StorageService::temporaryUrl()`.
- Public access to the MinIO storage endpoint is blocked.

---

## 3. Financial Maker-Checker Verification
- **Control Rule**: A financial transaction cannot be reversed by the same operator who created or posted it.
- **Verification Result**:
  - User A posts transaction `TX-100`.
  - User A attempts reversal $\rightarrow$ **REJECTED** with `403 MAKER_CHECKER_VIOLATION`.
  - User B (Manager) attempts reversal $\rightarrow$ **APPROVED**, posting reversing double-entry transaction `REV-TX-100`.

---

## 4. Settlement Payout Safety Controls
- **1Link IBFT Real Payouts**: **DISABLED** in production configuration.
- **Dispute Hold**: Any provider with an open dispute (`dispute_status = 'opened'`) cannot have a settlement batch generated.
- **Overdraw Protection**: Settlement batch amounts are strictly validated against net withdrawable earnings (Account 2020 payables minus Account 1030 COD debt and pending batches).
- **Idempotency**: All payout executions require an idempotency key (`SETTLEMENT-PAYOUT-{id}`), preventing duplicate bank disbursement calls.
