# Kaamly – Double-Entry Financial Ledger Audit & Test Report

**Date**: November 17, 2026  
**Auditor**: Antigravity Principal Architecture & QA Agent  
**Environment**: Laravel 13 Ledger Engine & Double-Entry Test Harness  
**Report Type**: Chart of Accounts, Immutability, Idempotency, and Accounting Workflows

---

## 1. Approved Chart of Accounts

| Account Code | Account Name | Type | Normal Balance | Purpose |
| :--- | :--- | :--- | :--- | :--- |
| **`1010`** | Platform Main Bank Account | Asset | Debit | Operational cash held in platform bank accounts. |
| **`1020`** | Payment Gateway Clearing | Asset | Debit | Funds authorized/captured via gateway, in-transit to bank. |
| **`1030`** | Provider Commission Receivable | Asset | Debit | Commission and penalty debts owed by technicians from COD jobs. |
| **`2010`** | Customer Escrow Holding | Liability | Credit | Fiduciary liability holding customer advance payments in escrow. |
| **`2020`** | Provider Payable | Liability | Credit | Earned job earnings owed to service providers. |
| **`3010`** | Platform Retained Earnings | Equity | Credit | Cumulative retained platform profit. |
| **`4010`** | Platform Commission Revenue | Revenue | Credit | Platform commission take recognized upon job completion. |
| **`4020`** | Customer & Diagnostic Fee Revenue | Revenue | Credit | Customer platform fees and inspection charges. |
| **`4030`** | Penalty & Cancellation Fee Revenue | Revenue | Credit | Liquidated damages from provider penalties and late cancellations. |

---

## 2. Mandatory Accounting Entries & Verification Results

### A. Online Prepaid Booking (Escrow & Split Completion)
```
1. Customer Funds Escrow:
   Debit:  1020 Payment Gateway Clearing       PKR 5,000.00
   Credit: 2010 Customer Escrow Holding         PKR 5,000.00

2. Job Completion Release:
   Debit:  2010 Customer Escrow Holding         PKR 5,000.00
   Credit: 2020 Provider Payable               PKR 4,500.00
   Credit: 4010 Platform Commission Revenue     PKR   500.00
```
- **Verification Result**: `Debits = Credits = PKR 5,000.00`. Zero escrow balance remained after completion. **PASSED**.

### B. Cash on Delivery (COD) Completion & Commission Accrual
```
1. Job Completion:
   Debit:  1030 Provider Commission Receivable  PKR 300.00
   Credit: 4010 Platform Commission Revenue     PKR 300.00
```
> [!IMPORTANT]
> The previous error debiting Account 2020 has been eliminated. Account 1030 is strictly debited, creating an Asset (debt owed by technician).

```
2. Technician Pays Commission Debt (via 1Link / Kuickpay):
   Debit:  1010 Platform Main Bank/Cash        PKR 300.00
   Credit: 1030 Provider Commission Receivable  PKR 300.00
```
- **Verification Result**: `Debits = Credits = PKR 300.00`. Account 1030 returned to zero; Account 1010 increased. **PASSED**.

### C. Provider Settlement Payout
```
Debit:  2020 Provider Payable                   PKR 2,000.00
Credit: 1010 Platform Main Bank                 PKR 2,000.00
```
- **Verification Result**: Provider earnings disbursed via bank transfer. **PASSED**.

### D. Customer Cancellation Post-Dispatch
```
Debit:  2010 Customer Escrow Holding            PKR 3,500.00
Credit: 2020 Provider Payable (Travel Fee)      PKR   500.00
Credit: 1020 Payment Gateway (Customer Refund)  PKR 3,000.00
```
- **Verification Result**: Provider compensated for dispatch; customer refunded remainder. **PASSED**.

### E. Provider Unexcused Cancellation / No-Show Penalty
```
Debit:  1030 Provider Commission Receivable     PKR 300.00 (or PKR 500 for No-Show)
Credit: 4030 Platform Penalty Fee Revenue       PKR 300.00 (or PKR 500 for No-Show)
```
- **Verification Result**: Penalty recognized as receivable debt without impacting customer escrow. **PASSED**.

---

## 3. Negative & Governance Test Results

| Test Case | Description | Expected Error | Actual Outcome | Status |
| :--- | :--- | :--- | :--- | :--- |
| **Imbalanced Transaction** | Debit PKR 1,000 / Credit PKR 900 | `LEDGER_UNBALANCED` | Blocked immediately | **PASSED** |
| **Non-Positive Amount** | Entry with amount PKR -500.00 | `NON_POSITIVE_AMOUNT` | Blocked immediately | **PASSED** |
| **Duplicate Webhook Post** | Same idempotency key replayed | `DUPLICATE_POSTING` | Replay blocked | **PASSED** |
| **Maker-Checker Violation** | User attempts to reverse own transaction | `MAKER_CHECKER_VIOLATION` | Blocked with 403 | **PASSED** |
| **COD Debt Threshold** | Provider owes $\ge$ PKR 2,000 accepting job | `DEBT_LIMIT_EXCEEDED` | Blocked with 403 | **PASSED** |

---

## 4. Wallet Balance Computation Rules
The provider wallet is a computed aggregate, never directly mutated:
$$\text{Gross Earned Payables} = \sum_{\text{posted}} \text{Credit}(2020) - \sum_{\text{posted}} \text{Debit}(2020)$$
$$\text{Outstanding COD Commission Debt} = \sum_{\text{posted}} \text{Debit}(1030) - \sum_{\text{posted}} \text{Credit}(1030)$$
$$\text{Net Withdrawable Balance} = \max(0, \text{Gross Earned Payables} - \text{Outstanding COD Debt} - \text{Pending Settlements})$$
$$\text{Is Debt Blocked} = (\text{Outstanding COD Debt} \ge 2000.00)$$
