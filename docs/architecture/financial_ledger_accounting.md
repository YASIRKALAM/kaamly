# KAAMLY PLATFORM – FINANCIAL ARCHITECTURE & DOUBLE-ENTRY ACCOUNTING LEDGER (CORRECTED)

## 1. Overview & Architectural Principles

Financial integrity on the **Kaamly** platform is governed by an immutable, append-only **Double-Entry General Ledger**. Single-sided wallet balances or mutable balance columns are strictly prohibited for all financial operations.

### Core Principles:
1. **Mathematical Invariant**: For every posted `ledger_transaction`, the sum of debits must strictly equal the sum of credits:
   $$\sum \text{Debit} = \sum \text{Credit}$$
2. **Immutability Enforced by Trigger**: Once a transaction transitions to status `posted`, neither the header nor its associated entries may be modified or deleted. Enforced by database trigger `trg_protect_ledger_entries`.
3. **Strict Separation of Receivables and Payables**:
   * **`1030 Provider Commission Receivable`** is an **Asset** account representing money owed *by* the provider *to* the platform (e.g., platform commissions on cash-collected jobs).
   * **`2020 Provider Payable`** is a **Liability** account representing net service earnings held *by* the platform that are owed *to* the provider (e.g., from online card bookings).
   * They are maintained as separate ledger accounts in the chart of accounts. The provider app can display a combined net wallet position, but underlying financial records are never blended.
4. **Idempotency**: Every financial operation requires a unique `idempotency_key` (e.g. `cod_settle_{booking_id}`) to prevent duplicate ledger postings across network retries and webhook deliveries.

---

## 2. Standardized Chart of Accounts

| Account Code | Account Name | Account Type | User-Scoped? | Normal Balance | Description |
| :--- | :--- | :--- | :---: | :---: | :--- |
| **`1010`** | `Platform Bank Account` | **Asset** | No (System) | Debit | Operating bank account holding platform liquid funds. |
| **`1020`** | `Gateway Clearing Account` | **Asset** | No (System) | Debit | Funds held in transit by payment processors (Stripe, JazzCash, EasyPaisa). |
| **`1030`** | `Provider Commission Receivable` | **Asset** | **Yes (`user_id`)**| **Debit** | Commission owed by a specific provider to the platform from COD bookings. |
| **`2010`** | `Customer Escrow Holding` | **Liability**| No (System) | Credit | Customer funds held in escrow pending service completion. |
| **`2020`** | `Provider Payable (Earnings)` | **Liability**| **Yes (`user_id`)**| **Credit** | Net earnings owed to a specific technician awaiting bank withdrawal. |
| **`2030`** | `Customer Wallet Balance` | **Liability**| Yes (`user_id`) | Credit | Customer store credits and advance prepayments. |
| **`4010`** | `Platform Commission Revenue` | **Revenue** | No (System) | Credit | Earned platform marketplace commission fees. |
| **`4020`** | `Customer Service Fee Revenue`| **Revenue** | No (System) | Credit | Fixed booking service fee charged to customers. |
| **`5010`** | `Gateway Processing Fees` | **Expense** | No (System) | Debit | Transaction fees charged by payment gateways. |
| **`5020`** | `Operational Goodwill Expense`| **Expense** | No (System) | Debit | Platform-funded customer concessions or dispute compensations. |

---

## 3. Comprehensive Lifecycle Accounting Scenarios

### Scenario A: Cash on Delivery (COD) Booking Completed (Corrected)
* **Context**: Customer books an Electrician service for PKR 3,000. Payment method is `cash_on_delivery`. The technician collects PKR 3,000 in cash directly from the customer at the job site. The agreed platform commission is 10% (PKR 300).
* **Accounting Reality**: The customer pays the technician directly in cash. The technician now owes the platform its PKR 300 commission. This is an **Asset Receivable**, not a liability debit.
* **Trigger**: Provider confirms cash collection and enters the customer's 6-digit PIN.
* **Transaction Reference**: `cod_commission_charge_{booking_id}`

| Entry | Account Code | Account Title | Type | Debit (PKR) | Credit (PKR) | Memo |
| :---: | :---: | :--- | :---: | :---: | :---: | :--- |
| 1 | `1030` | Provider Commission Receivable: Provider Y | Asset | **300.00** | - | Provider Y owes platform 10% commission on COD job |
| 2 | `4010` | Platform Commission Revenue | Revenue | - | **300.00** | Realize earned platform commission revenue |

$$\sum \text{Debit} = 300.00 \quad \Big| \quad \sum \text{Credit} = 300.00 \quad \checkmark$$

---

### Scenario B: Provider Clears Outstanding COD Receivable (Bank / Mobile Transfer)
* **Context**: Provider Y pays PKR 300 to Kaamly via EasyPaisa / 1LINK to settle their commission debt.
* **Trigger**: Verified inbound bank payment.
* **Transaction Reference**: `provider_debt_payment_{provider_id}_{ref}`

| Entry | Account Code | Account Title | Type | Debit (PKR) | Credit (PKR) | Memo |
| :---: | :---: | :--- | :---: | :---: | :---: | :--- |
| 1 | `1010` | Platform Bank Account | Asset | **300.00** | - | Inflow into platform bank account |
| 2 | `1030` | Provider Commission Receivable: Provider Y | Asset | - | **300.00** | Clear outstanding commission debt for Provider Y |

$$\sum \text{Debit} = 300.00 \quad \Big| \quad \sum \text{Credit} = 300.00 \quad \checkmark$$

---

### Scenario C: Automated Internal Offset (Online Payable Offsets COD Receivable)
* **Context**: Provider Y has PKR 1,000 in `2020 Provider Payable` from online card jobs, and also owes PKR 300 in `1030 Provider Commission Receivable` from a COD job. The platform executes an automated internal clearing settlement.
* **Trigger**: End-of-day billing reconciliation or withdrawal request.
* **Transaction Reference**: `offset_clearing_{provider_id}_{date}`

| Entry | Account Code | Account Title | Type | Debit (PKR) | Credit (PKR) | Memo |
| :---: | :---: | :--- | :---: | :---: | :---: | :--- |
| 1 | `2020` | Provider Payable: Provider Y | Liability | **300.00** | - | Reduce platform liability owed to provider |
| 2 | `1030` | Provider Commission Receivable: Provider Y | Asset | - | **300.00** | Extinguish provider commission receivable debt |

$$\sum \text{Debit} = 300.00 \quad \Big| \quad \sum \text{Credit} = 300.00 \quad \checkmark$$

---

### Scenario D: Online Payment Upfront (Escrow Hold)
* **Context**: Customer books an AC Deep Clean service for PKR 5,000 + PKR 100 customer platform fee via credit card.
* **Trigger**: Payment Gateway webhook confirms charge capture.
* **Transaction Reference**: `escrow_capture_{booking_id}`

| Entry | Account Code | Account Title | Type | Debit (PKR) | Credit (PKR) | Memo |
| :---: | :---: | :--- | :---: | :---: | :---: | :--- |
| 1 | `1020` | Gateway Clearing Account | Asset | **5,100.00** | - | Inbound funds held at payment gateway |
| 2 | `2010` | Customer Escrow Holding | Liability | - | **5,100.00** | Customer funds held in trust pending completion |

$$\sum \text{Debit} = 5,100.00 \quad \Big| \quad \sum \text{Credit} = 5,100.00 \quad \checkmark$$

---

### Scenario E: Online Booking Completed (Escrow Release to Provider & Revenue)
* **Context**: Technician completes the job. Customer verifies start/completion PIN. Agreed price: PKR 5,000. Customer fee: PKR 100. Commission rate: 10% (PKR 500). Net provider earning: PKR 4,500.
* **Trigger**: Booking status transitions to `completed`.
* **Transaction Reference**: `escrow_release_{booking_id}`

| Entry | Account Code | Account Title | Type | Debit (PKR) | Credit (PKR) | Memo |
| :---: | :---: | :--- | :---: | :---: | :---: | :--- |
| 1 | `2010` | Customer Escrow Holding | Liability | **5,100.00** | - | Release funds from escrow |
| 2 | `4010` | Platform Commission Revenue | Revenue | - | **500.00** | 10% platform commission earned |
| 3 | `4020` | Customer Service Fee Revenue | Revenue | - | **100.00** | Customer platform fee earned |
| 4 | `2020` | Provider Payable: Technician X | Liability | - | **4,500.00** | Net earnings payable to technician |

$$\sum \text{Debit} = 5,100.00 \quad \Big| \quad \sum \text{Credit} = 500.00 + 100.00 + 4,500.00 = 5,100.00 \quad \checkmark$$

---

### Scenario F: Provider Withdrawal Settlement (Payout to Bank)
* **Context**: Technician X has accumulated PKR 15,000 in `2020 Provider Payable` and requests a withdrawal payout of PKR 10,000 to their registered bank account.
* **Trigger**: Settlement batch processed and confirmed via 1LINK / IBFT.
* **Transaction Reference**: `settlement_payout_{settlement_id}`

| Entry | Account Code | Account Title | Type | Debit (PKR) | Credit (PKR) | Memo |
| :---: | :---: | :--- | :---: | :---: | :---: | :--- |
| 1 | `2020` | Provider Payable: Technician X | Liability | **10,000.00** | - | Discharge platform liability owed to technician |
| 2 | `1010` | Platform Bank Account | Asset | - | **10,000.00** | Outflow from platform bank account |

$$\sum \text{Debit} = 10,000.00 \quad \Big| \quad \sum \text{Credit} = 10,000.00 \quad \checkmark$$

---

### Scenario G: Cancellation & Full Customer Refund (Online Escrow)
* **Context**: Customer cancels prepaid booking (PKR 5,100) before technician dispatch.
* **Trigger**: Booking status transitions to `cancelled`.
* **Transaction Reference**: `booking_refund_{booking_id}`

| Entry | Account Code | Account Title | Type | Debit (PKR) | Credit (PKR) | Memo |
| :---: | :---: | :--- | :---: | :---: | :---: | :--- |
| 1 | `2010` | Customer Escrow Holding | Liability | **5,100.00** | - | Reverse customer escrow liability |
| 2 | `1020` | Gateway Clearing Account | Asset | - | **5,100.00** | Gateway refund issued back to customer card |

$$\sum \text{Debit} = 5,100.00 \quad \Big| \quad \sum \text{Credit} = 5,100.00 \quad \checkmark$$

---

### Scenario H: Dispute Resolution (Partial Refund & Partial Payout)
* **Context**: PKR 4,000 held in escrow. Workmanship dispute arbitrated by Admin: customer awarded 50% refund (PKR 2,000); provider awarded 50% payout (PKR 2,000 less 10% commission = PKR 1,800 net, PKR 200 platform commission).
* **Trigger**: Dispute status marked `arbitrated_mutual_agreement`.
* **Transaction Reference**: `dispute_settle_{dispute_id}`

| Entry | Account Code | Account Title | Type | Debit (PKR) | Credit (PKR) | Memo |
| :---: | :---: | :--- | :---: | :---: | :---: | :--- |
| 1 | `2010` | Customer Escrow Holding | Liability | **4,000.00** | - | Release disputed escrow funds |
| 2 | `1020` | Gateway Clearing Account | Asset | - | **2,000.00** | 50% refund to customer card |
| 3 | `4010` | Platform Commission Revenue | Revenue | - | **200.00** | 10% commission on awarded work |
| 4 | `2020` | Provider Payable: Provider Z | Liability | - | **1,800.00** | Net earning awarded to provider |

$$\sum \text{Debit} = 4,000.00 \quad \Big| \quad \sum \text{Credit} = 2,000.00 + 200.00 + 1,800.00 = 4,000.00 \quad \checkmark$$

---

### Scenario I: Reversal of Erroneous Posting
* **Context**: A manual adjustment of PKR 500 was posted in error under transaction `TXN-001`.
* **Trigger**: Admin reversal authorized.
* **Rule**: Posted transactions cannot be deleted. An equal and opposite reversal transaction `TXN-001-REV` is posted.

| Entry | Account Code | Account Title | Type | Debit (PKR) | Credit (PKR) | Memo |
| :---: | :---: | :--- | :---: | :---: | :---: | :--- |
| 1 | `2030` | Customer Wallet Balance | Liability | **500.00** | - | Reversal: Debit customer wallet balance |
| 2 | `5020` | Operational Goodwill Expense | Expense | - | **500.00** | Reversal: Credit goodwill expense |

---

## 4. Derived Provider Mobile App Wallet Position

In the mobile app, the provider sees their consolidated wallet status derived directly from the chart of accounts:

$$\text{Gross Withdrawable Earnings} = \sum \text{Credit}_{(2020)} - \sum \text{Debit}_{(2020)}$$
$$\text{Outstanding COD Commission Debt} = \sum \text{Debit}_{(1030)} - \sum \text{Credit}_{(1030)}$$
$$\mathbf{\text{Net Wallet Balance}} = \text{Gross Withdrawable Earnings} - \text{Outstanding COD Commission Debt}$$

* **Negative Limit Enforcement**: If $\text{Outstanding COD Commission Debt} > \text{PKR } 2,000.00$ (or $\text{Net Wallet Balance} < -\text{PKR } 2,000.00$), the system automatically sets `provider_profiles.is_online = FALSE` and rejects new job acceptances until settled.
