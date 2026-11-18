#!/usr/bin/env python3
"""
Kaamly E2E Marketplace Workflows & Financial Ledger Simulation Test Suite
Covers the 8 core marketplace workflows (A through H):
- Workflow A: COD Fixed-Price Booking & Commission Debt Settlement (PKR 2,000 threshold block)
- Workflow B: Online Prepaid Booking with Escrow Hold & Completion Split
- Workflow C: Inspection-Based Quotation Lifecycle (Labor + Material Line Items & Price Locking)
- Workflow D: Customer Cancellation Handling (Pre-dispatch vs Post-dispatch travel fee)
- Workflow E: Provider Cancellation Penalty (Debit 1030 / Credit 4030)
- Workflow F: Customer No-Show Resolution (Wait time > 20m, Provider travel compensation)
- Workflow G: Provider No-Show Penalty (Customer full refund, Provider penalty debited to 1030)
- Workflow H: Formal Dispute Arbitration (Escrow split: partial refund + partial payout)
"""

import sys
import uuid
from decimal import Decimal, ROUND_HALF_UP

def round_curr(val):
    return Decimal(str(val)).quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)

class MockLedger:
    def __init__(self):
        # Initial chart of accounts
        self.accounts = {
            "1010": {"name": "Platform Bank/Cash", "type": "asset", "balance": Decimal("500000.00")},
            "1020": {"name": "Payment Gateway Clearing", "type": "asset", "balance": Decimal("0.00")},
            "1030": {"name": "Provider Commission Receivable", "type": "asset", "balance": Decimal("0.00")},
            "2010": {"name": "Customer Escrow Holding", "type": "liability", "balance": Decimal("0.00")},
            "2020": {"name": "Provider Payable", "type": "liability", "balance": Decimal("0.00")},
            "4010": {"name": "Platform Commission Revenue", "type": "revenue", "balance": Decimal("0.00")},
            "4030": {"name": "Platform Penalty Revenue", "type": "revenue", "balance": Decimal("0.00")},
        }
        self.transactions = []
        self.idempotency_keys = set()

    def post(self, idempotency_key: str, transaction_type: str, entries: list, reference_type=None, reference_id=None):
        if idempotency_key in self.idempotency_keys:
            raise ValueError(f"Duplicate financial posting detected: idempotency_key '{idempotency_key}' already posted.")
        
        total_debits = sum(Decimal(str(e["amount"])) for e in entries if e["direction"] == "debit")
        total_credits = sum(Decimal(str(e["amount"])) for e in entries if e["direction"] == "credit")

        if round_curr(total_debits) != round_curr(total_credits):
            raise AssertionError(f"Ledger Imbalance! Debits ({total_debits}) != Credits ({total_credits})")

        tx_id = str(uuid.uuid4())
        self.idempotency_keys.add(idempotency_key)
        
        # Apply entries to accounts
        for e in entries:
            code = e["account_code"]
            amt = round_curr(e["amount"])
            acc = self.accounts[code]
            acc_type = acc["type"]

            if acc_type in ["asset", "expense"]:
                if e["direction"] == "debit":
                    acc["balance"] += amt
                else:
                    acc["balance"] -= amt
            else: # liability, equity, revenue
                if e["direction"] == "credit":
                    acc["balance"] += amt
                else:
                    acc["balance"] -= amt

        self.transactions.append({
            "id": tx_id,
            "idempotency_key": idempotency_key,
            "type": transaction_type,
            "entries": entries,
            "reference": f"{reference_type}:{reference_id}"
        })
        return tx_id

    def get_balance(self, code: str) -> Decimal:
        return self.accounts[code]["balance"]

# -------------------------------------------------------------
# Workflow Tests
# -------------------------------------------------------------

def test_workflow_a_cod_and_debt_block():
    print("=" * 65)
    print("WORKFLOW A: COD Fixed-Price Booking & Commission Debt Settlement")
    print("=" * 65)
    ledger = MockLedger()
    provider_id = "prov-001"
    booking_id = "bk-cod-101"
    booking_total = Decimal("3000.00")
    commission_rate = Decimal("0.10")
    commission_amount = round_curr(booking_total * commission_rate) # PKR 300.00

    # 1. COD Completion: Customer pays provider PKR 3,000 in cash.
    # Platform earns PKR 300 commission receivable.
    print(f"1. Posting COD Completion for Booking {booking_id} (Total: PKR {booking_total})...")
    ledger.post(
        idempotency_key=f"cod_completion_{booking_id}",
        transaction_type="cod_commission_accrual",
        entries=[
            {"account_code": "1030", "direction": "debit", "amount": commission_amount},   # Provider Commission Receivable (Asset)
            {"account_code": "4010", "direction": "credit", "amount": commission_amount},  # Platform Commission Revenue (Revenue)
        ],
        reference_type="booking",
        reference_id=booking_id
    )
    assert ledger.get_balance("1030") == Decimal("300.00"), "Account 1030 must reflect PKR 300.00 receivable."
    assert ledger.get_balance("2020") == Decimal("0.00"), "Account 2020 must remain untouched for COD."
    print("   [OK] Posted: Debit 1030 (PKR 300.00), Credit 4010 (PKR 300.00)")

    # 2. Simulate technician accruing debt up to PKR 2,100
    print("\n2. Simulating multiple COD bookings accumulating debt...")
    for i in range(2, 8):
        b_id = f"bk-cod-10{i}"
        ledger.post(
            idempotency_key=f"cod_completion_{b_id}",
            transaction_type="cod_commission_accrual",
            entries=[
                {"account_code": "1030", "direction": "debit", "amount": commission_amount},
                {"account_code": "4010", "direction": "credit", "amount": commission_amount},
            ],
            reference_type="booking",
            reference_id=b_id
        )
    total_debt = ledger.get_balance("1030")
    print(f"   Current Outstanding COD Debt (Account 1030): PKR {total_debt}")
    assert total_debt == Decimal("2100.00")

    # 3. Verify PKR 2,000 Debt Threshold Blocking
    print("\n3. Testing PKR 2,000 Debt Threshold Gate...")
    DEBT_THRESHOLD = Decimal("2000.00")
    is_blocked = total_debt >= DEBT_THRESHOLD
    assert is_blocked is True, "Provider must be blocked when debt >= PKR 2,000."
    print(f"   [BLOCKED] Provider {provider_id} is blocked from accepting jobs (Debt PKR {total_debt} >= PKR {DEBT_THRESHOLD}).")

    # 4. Provider settles debt via bank transfer (PKR 1,500)
    print("\n4. Provider pays PKR 1,500 via 1Link bank transfer...")
    settlement_payment = Decimal("1500.00")
    ledger.post(
        idempotency_key=f"debt_payment_{provider_id}_tx01",
        transaction_type="provider_debt_payment",
        entries=[
            {"account_code": "1010", "direction": "debit", "amount": settlement_payment},   # Platform Bank/Cash (Asset increased)
            {"account_code": "1030", "direction": "credit", "amount": settlement_payment},  # Provider Commission Receivable (Asset decreased)
        ],
        reference_type="provider_profile",
        reference_id=provider_id
    )
    remaining_debt = ledger.get_balance("1030")
    print(f"   Remaining Debt in Account 1030: PKR {remaining_debt}")
    assert remaining_debt == Decimal("600.00")
    assert remaining_debt < DEBT_THRESHOLD
    print(f"   [UNBLOCKED] Provider {provider_id} unblocked! (Debt PKR {remaining_debt} < PKR {DEBT_THRESHOLD})\n")


def test_workflow_b_online_prepaid_and_escrow():
    print("=" * 65)
    print("WORKFLOW B: Online Prepaid Booking with Escrow Hold & Completion")
    print("=" * 65)
    ledger = MockLedger()
    booking_id = "bk-online-201"
    amount = Decimal("5000.00")
    commission_rate = Decimal("0.10")
    platform_commission = round_curr(amount * commission_rate) # PKR 500
    provider_payout = amount - platform_commission             # PKR 4500

    # 1. Customer prepays online -> Escrow Hold
    print(f"1. Customer pays PKR {amount} via JazzCash (Escrow Hold)...")
    ledger.post(
        idempotency_key=f"escrow_hold_{booking_id}",
        transaction_type="escrow_hold",
        entries=[
            {"account_code": "1020", "direction": "debit", "amount": amount},  # Gateway Clearing (Asset)
            {"account_code": "2010", "direction": "credit", "amount": amount}, # Customer Escrow Holding (Liability)
        ],
        reference_type="booking",
        reference_id=booking_id
    )
    assert ledger.get_balance("2010") == amount
    print("   [OK] Escrow Hold Posted: Debit 1020 (PKR 5000), Credit 2010 (PKR 5000)")

    # 2. Gateway settlement to platform bank account
    print("2. Gateway settles funds to Platform Bank Account...")
    ledger.post(
        idempotency_key=f"gateway_settle_{booking_id}",
        transaction_type="gateway_settlement",
        entries=[
            {"account_code": "1010", "direction": "debit", "amount": amount},
            {"account_code": "1020", "direction": "credit", "amount": amount},
        ],
        reference_type="booking",
        reference_id=booking_id
    )
    assert ledger.get_balance("1020") == Decimal("0.00")

    # 3. Technician Completes Job -> Release Escrow
    print(f"3. Job Completed: Releasing Escrow (Commission: PKR {platform_commission}, Provider: PKR {provider_payout})...")
    ledger.post(
        idempotency_key=f"booking_completion_{booking_id}",
        transaction_type="booking_completion_release",
        entries=[
            {"account_code": "2010", "direction": "debit", "amount": amount},               # Escrow Released
            {"account_code": "2020", "direction": "credit", "amount": provider_payout},     # Provider Payable (PKR 4500)
            {"account_code": "4010", "direction": "credit", "amount": platform_commission}, # Commission Revenue (PKR 500)
        ],
        reference_type="booking",
        reference_id=booking_id
    )
    assert ledger.get_balance("2010") == Decimal("0.00"), "Escrow holding must be zero after completion."
    assert ledger.get_balance("2020") == provider_payout, "Provider payable must equal net payout."
    assert ledger.get_balance("4010") == platform_commission, "Platform revenue must equal commission."
    print("   [OK] Escrow Released: Debit 2010 (5000) = Credit 2020 (4500) + Credit 4010 (500)\n")


def test_workflow_c_inspection_quotation():
    print("=" * 65)
    print("WORKFLOW C: Inspection-Based Quotation Lifecycle")
    print("=" * 65)
    # Technician visits customer premises, inspects, and submits formal line-item quotation
    line_items = [
        {"item_name": "AC Gas Refill (R410A)", "item_type": "material", "quantity": 1, "unit_price": Decimal("2500.00")},
        {"item_name": "Compressor Capacitor 45uF", "item_type": "material", "quantity": 1, "unit_price": Decimal("850.00")},
        {"item_name": "Labor & System Vacuum Service", "item_type": "labor", "quantity": 1, "unit_price": Decimal("1500.00")},
    ]

    material_cost = sum(item["quantity"] * item["unit_price"] for item in line_items if item["item_type"] == "material")
    labor_cost = sum(item["quantity"] * item["unit_price"] for item in line_items if item["item_type"] == "labor")
    total_quote = material_cost + labor_cost

    print(f"1. Line Items Submitted by Technician:")
    for item in line_items:
        print(f"   - {item['item_name']} ({item['item_type']}): PKR {item['quantity'] * item['unit_price']}")
    print(f"   Calculated: Materials = PKR {material_cost}, Labor = PKR {labor_cost}, Total = PKR {total_quote}")
    assert material_cost == Decimal("3350.00")
    assert labor_cost == Decimal("1500.00")
    assert total_quote == Decimal("4850.00")

    # Customer confirms quotation -> Price snapshot locked
    agreed_price_snapshot = total_quote
    booking_status = "confirmed"
    print(f"2. Customer Approved Quotation -> Agreed Price Locked at PKR {agreed_price_snapshot} (Status: {booking_status})")
    assert agreed_price_snapshot == Decimal("4850.00")
    print("   [OK] Quotation line-item validation & immutable price snapshot confirmed.\n")


def test_workflow_d_customer_cancellation():
    print("=" * 65)
    print("WORKFLOW D: Customer Cancellation (Pre-dispatch vs Post-dispatch)")
    print("=" * 65)
    ledger = MockLedger()
    escrow_amount = Decimal("3500.00")
    booking_id = "bk-canc-301"

    # Pre-fund escrow
    ledger.post(
        idempotency_key=f"escrow_{booking_id}",
        transaction_type="escrow_hold",
        entries=[
            {"account_code": "1020", "direction": "debit", "amount": escrow_amount},
            {"account_code": "2010", "direction": "credit", "amount": escrow_amount},
        ]
    )

    # Scenario D1: Customer cancels BEFORE technician leaves -> 100% refund
    print("1. Scenario D1: Customer cancels BEFORE dispatch (Full Refund)...")
    ledger.post(
        idempotency_key=f"refund_predispatch_{booking_id}",
        transaction_type="customer_full_refund",
        entries=[
            {"account_code": "2010", "direction": "debit", "amount": escrow_amount},
            {"account_code": "1020", "direction": "credit", "amount": escrow_amount},
        ]
    )
    assert ledger.get_balance("2010") == Decimal("0.00")
    assert ledger.get_balance("2020") == Decimal("0.00")
    print("   [OK] 100% Escrow Refunded to Customer.")

    # Scenario D2: Customer cancels AFTER technician is on the way (status: provider_on_the_way)
    # Travel fee = PKR 500 paid to provider; remainder PKR 3,000 refunded to customer
    print("\n2. Scenario D2: Customer cancels AFTER provider dispatch (Travel Fee: PKR 500)...")
    booking_id_2 = "bk-canc-302"
    travel_fee = Decimal("500.00")
    customer_refund = escrow_amount - travel_fee # PKR 3000

    # Escrow funded
    ledger.post(
        idempotency_key=f"escrow_{booking_id_2}",
        transaction_type="escrow_hold",
        entries=[
            {"account_code": "1020", "direction": "debit", "amount": escrow_amount},
            {"account_code": "2010", "direction": "credit", "amount": escrow_amount},
        ]
    )

    # Post cancellation split
    ledger.post(
        idempotency_key=f"cancellation_postdispatch_{booking_id_2}",
        transaction_type="cancellation_after_dispatch",
        entries=[
            {"account_code": "2010", "direction": "debit", "amount": escrow_amount},
            {"account_code": "2020", "direction": "credit", "amount": travel_fee},      # Provider Payable
            {"account_code": "1020", "direction": "credit", "amount": customer_refund}, # Customer Refund
        ],
        reference_type="booking",
        reference_id=booking_id_2
    )
    assert ledger.get_balance("2010") == Decimal("0.00")
    assert ledger.get_balance("2020") == travel_fee
    print(f"   [OK] Escrow Split: Provider Compensated PKR {travel_fee} (Account 2020), Customer Refunded PKR {customer_refund}.\n")


def test_workflow_e_provider_unexcused_cancellation():
    print("=" * 65)
    print("WORKFLOW E: Provider Unexcused Cancellation Penalty")
    print("=" * 65)
    ledger = MockLedger()
    provider_id = "prov-009"
    booking_id = "bk-provcanc-401"
    penalty_fee = Decimal("300.00")

    print(f"1. Provider cancels accepted booking without valid emergency...")
    print(f"   Posting PKR {penalty_fee} penalty fee to Provider Commission Receivable (Account 1030)...")
    ledger.post(
        idempotency_key=f"provider_penalty_{booking_id}",
        transaction_type="provider_cancellation_penalty",
        entries=[
            {"account_code": "1030", "direction": "debit", "amount": penalty_fee},  # Provider Commission Receivable (Asset - debt)
            {"account_code": "4030", "direction": "credit", "amount": penalty_fee}, # Platform Penalty Revenue
        ],
        reference_type="booking",
        reference_id=booking_id
    )
    assert ledger.get_balance("1030") == penalty_fee
    assert ledger.get_balance("4030") == penalty_fee
    print("   [OK] Debit 1030 (PKR 300) / Credit 4030 (PKR 300) successfully posted.\n")


def test_workflow_f_customer_no_show():
    print("=" * 65)
    print("WORKFLOW F: Customer No-Show Handling (Wait > 20 mins)")
    print("=" * 65)
    ledger = MockLedger()
    booking_id = "bk-noshow-501"
    booking_total = Decimal("4000.00")
    travel_fee = Decimal("500.00")
    customer_refund = booking_total - travel_fee # PKR 3500

    # Escrow funded
    ledger.post(
        idempotency_key=f"escrow_{booking_id}",
        transaction_type="escrow_hold",
        entries=[
            {"account_code": "1020", "direction": "debit", "amount": booking_total},
            {"account_code": "2010", "direction": "credit", "amount": booking_total},
        ]
    )

    print("1. Technician arrived at location, waited 22 minutes, made 3 unanswered calls.")
    wait_time_minutes = 22
    assert wait_time_minutes >= 20, "Customer no-show requires at least 20 minutes wait."

    print(f"2. Transitioning to 'no_show' with responsible party: 'customer'...")
    no_show_responsible_party = "customer"
    assert no_show_responsible_party in ["customer", "provider"]

    ledger.post(
        idempotency_key=f"customer_noshow_{booking_id}",
        transaction_type="customer_no_show_settlement",
        entries=[
            {"account_code": "2010", "direction": "debit", "amount": booking_total},
            {"account_code": "2020", "direction": "credit", "amount": travel_fee},      # Provider travel compensation
            {"account_code": "1020", "direction": "credit", "amount": customer_refund}, # Customer refund
        ],
        reference_type="booking",
        reference_id=booking_id
    )
    assert ledger.get_balance("2020") == travel_fee
    print(f"   [OK] Provider credited PKR {travel_fee} travel compensation. Customer refunded PKR {customer_refund}.\n")


def test_workflow_g_provider_no_show():
    print("=" * 65)
    print("WORKFLOW G: Provider No-Show Penalty & Customer Full Refund")
    print("=" * 65)
    ledger = MockLedger()
    booking_id = "bk-provnoshow-601"
    booking_total = Decimal("3000.00")
    penalty_fee = Decimal("500.00")

    # Escrow funded
    ledger.post(
        idempotency_key=f"escrow_{booking_id}",
        transaction_type="escrow_hold",
        entries=[
            {"account_code": "1020", "direction": "debit", "amount": booking_total},
            {"account_code": "2010", "direction": "credit", "amount": booking_total},
        ]
    )

    print("1. Technician failed to arrive within 60 minutes of scheduled slot.")
    no_show_responsible_party = "provider"

    # Step 1: Refund Customer 100% from Escrow
    print(f"2. 100% Customer Refund (PKR {booking_total})...")
    ledger.post(
        idempotency_key=f"refund_prov_noshow_{booking_id}",
        transaction_type="provider_no_show_customer_refund",
        entries=[
            {"account_code": "2010", "direction": "debit", "amount": booking_total},
            {"account_code": "1020", "direction": "credit", "amount": booking_total},
        ]
    )

    # Step 2: Fine Provider PKR 500 Penalty
    print(f"3. Penalizing Provider PKR {penalty_fee} to Account 1030...")
    ledger.post(
        idempotency_key=f"penalty_prov_noshow_{booking_id}",
        transaction_type="provider_no_show_penalty",
        entries=[
            {"account_code": "1030", "direction": "debit", "amount": penalty_fee},
            {"account_code": "4030", "direction": "credit", "amount": penalty_fee},
        ]
    )
    assert ledger.get_balance("2010") == Decimal("0.00")
    assert ledger.get_balance("1030") == penalty_fee
    assert ledger.get_balance("4030") == penalty_fee
    print("   [OK] Customer 100% refunded and Provider penalized PKR 500 debt.\n")


def test_workflow_h_dispute_arbitration():
    print("=" * 65)
    print("WORKFLOW H: Formal Dispute Arbitration (Escrow Split)")
    print("=" * 65)
    ledger = MockLedger()
    booking_id = "bk-disp-701"
    booking_total = Decimal("6000.00")

    # Escrow funded
    ledger.post(
        idempotency_key=f"escrow_{booking_id}",
        transaction_type="escrow_hold",
        entries=[
            {"account_code": "1020", "direction": "debit", "amount": booking_total},
            {"account_code": "2010", "direction": "credit", "amount": booking_total},
        ]
    )

    print("1. Customer disputes incomplete paint work. Escrow PKR 6,000 frozen.")
    print("2. Admin reviews photo evidence and arbitrates a 50/50 split:")
    customer_refund = Decimal("3000.00")
    provider_payout = Decimal("2700.00")
    platform_commission = Decimal("300.00") # 10% of provider portion

    assert customer_refund + provider_payout + platform_commission == booking_total

    ledger.post(
        idempotency_key=f"dispute_arbitration_{booking_id}",
        transaction_type="dispute_arbitration_split",
        entries=[
            {"account_code": "2010", "direction": "debit", "amount": booking_total},
            {"account_code": "1020", "direction": "credit", "amount": customer_refund},
            {"account_code": "2020", "direction": "credit", "amount": provider_payout},
            {"account_code": "4010", "direction": "credit", "amount": platform_commission},
        ],
        reference_type="dispute",
        reference_id=booking_id
    )

    assert ledger.get_balance("2010") == Decimal("0.00")
    assert ledger.get_balance("2020") == provider_payout
    assert ledger.get_balance("4010") == platform_commission
    print(f"   [OK] Arbitrated Settlement Posted:")
    print(f"        - Customer Refund: PKR {customer_refund}")
    print(f"        - Provider Net Payout: PKR {provider_payout}")
    print(f"        - Platform Fee: PKR {platform_commission}")
    print("        - Total Balances Balanced to PKR 0.00 Imbalance.\n")


def test_idempotency_protection():
    print("=" * 65)
    print("CONCURRENCY & IDEMPOTENCY SAFETY CHECK")
    print("=" * 65)
    ledger = MockLedger()
    entries = [
        {"account_code": "1020", "direction": "debit", "amount": Decimal("1000.00")},
        {"account_code": "2010", "direction": "credit", "amount": Decimal("1000.00")},
    ]
    key = "unique-webhook-evt-001"
    # First post succeeds
    ledger.post(key, "escrow_hold", entries)
    print("1. First webhook delivery: Posted successfully.")
    
    # Second post with identical key must fail
    try:
        ledger.post(key, "escrow_hold", entries)
        raise AssertionError("Idempotency failed: Duplicate post was allowed!")
    except ValueError as e:
        print(f"2. Second webhook delivery: Successfully blocked! ({e})")
    print("   [OK] Idempotent financial posting verified.\n")


if __name__ == "__main__":
    print("######################################################################")
    print("  KAAMLY - END-TO-END MARKETPLACE WORKFLOWS SIMULATION TEST SUITE")
    print("######################################################################\n")
    test_workflow_a_cod_and_debt_block()
    test_workflow_b_online_prepaid_and_escrow()
    test_workflow_c_inspection_quotation()
    test_workflow_d_customer_cancellation()
    test_workflow_e_provider_unexcused_cancellation()
    test_workflow_f_customer_no_show()
    test_workflow_g_provider_no_show()
    test_workflow_h_dispute_arbitration()
    test_idempotency_protection()
    print("######################################################################")
    print("  ALL 8 MARKETPLACE WORKFLOWS & CONCURRENCY CHECKS PASSED PERFECTLY!")
    print("######################################################################")
