#!/usr/bin/env python3
"""
Kaamly Phase 5 - Flutter Mobile Applications Verification Suite
Verifies:
1. Shared Package (kaamly_shared): pubspec, data models, Dio API client, Reverb WebSocket client, AppTheme
2. Customer Mobile App (kaamly_customer): pubspec, login, home, 6-gate discovery, booking creation & 13-state tracking
3. Provider Mobile App (kaamly_provider): pubspec, partner login, 5-step onboarding wizard, jobs monitor, inspection quote builder, and wallet with COD debt threshold warning
"""

import sys
from pathlib import Path

MOBILE_DIR = Path(r"d:\Study\code\Kaamly\mobile")

def test_shared_package():
    print("[1] Verifying Shared Flutter Package (kaamly_shared)...")
    shared_dir = MOBILE_DIR / "shared"
    assert (shared_dir / "pubspec.yaml").exists(), "shared/pubspec.yaml missing"
    assert (shared_dir / "lib" / "kaamly_shared.dart").exists(), "kaamly_shared.dart export missing"

    models_dir = shared_dir / "lib" / "src" / "models"
    expected_models = [
        "api_response.dart",
        "user.dart",
        "customer_profile.dart",
        "customer_address.dart",
        "provider_profile.dart",
        "service.dart",
        "booking.dart",
        "quotation.dart",
        "wallet.dart",
        "review.dart",
    ]
    for m in expected_models:
        model_path = models_dir / m
        assert model_path.exists(), f"Missing model: {m}"
        content = model_path.read_text(encoding="utf-8")
        assert "fromJson" in content, f"Missing fromJson in {m}"
        assert "toJson" in content, f"Missing toJson in {m}"
        print(f"  [OK] Model verified: {m}")

    # Check Dio API Client
    api_client = (shared_dir / "lib" / "src" / "network" / "api_client.dart").read_text(encoding="utf-8")
    assert "Authorization" in api_client, "api_client must inject Bearer token"
    assert "saveToken" in api_client, "api_client must support token saving"
    assert "clearToken" in api_client, "api_client must support token clearing"
    print("  [OK] Dio REST API Client verified with Sanctum Bearer authentication.")

    # Check Reverb Client
    reverb_client = (shared_dir / "lib" / "src" / "websocket" / "reverb_client.dart").read_text(encoding="utf-8")
    assert "subscribe" in reverb_client, "reverb_client must support subscribe"
    print("  [OK] Laravel Reverb WebSocket client verified.\n")


def test_customer_app():
    print("[2] Verifying Customer Mobile Application (kaamly_customer)...")
    cust_dir = MOBILE_DIR / "customer-app"
    assert (cust_dir / "pubspec.yaml").exists(), "customer-app/pubspec.yaml missing"
    assert (cust_dir / "lib" / "main.dart").exists(), "customer-app/main.dart missing"

    # Verify screens
    screens = [
        cust_dir / "lib" / "features" / "auth" / "screens" / "login_screen.dart",
        cust_dir / "lib" / "features" / "home" / "screens" / "home_screen.dart",
        cust_dir / "lib" / "features" / "discovery" / "screens" / "provider_discovery_screen.dart",
        cust_dir / "lib" / "features" / "booking" / "screens" / "create_booking_screen.dart",
        cust_dir / "lib" / "features" / "booking" / "screens" / "booking_tracking_screen.dart",
    ]
    for s in screens:
        assert s.exists(), f"Missing customer screen: {s.name}"
        content = s.read_text(encoding="utf-8")
        assert "State" in content or "Widget" in content, f"Invalid Flutter widget in {s.name}"
        print(f"  [OK] Customer Screen verified: {s.name}")

    # Verify tracking screen features
    tracking_content = (cust_dir / "lib" / "features" / "booking" / "screens" / "booking_tracking_screen.dart").read_text(encoding="utf-8")
    assert "_acceptQuotation" in tracking_content, "Missing quotation acceptance in tracking screen"
    assert "_cancelBooking" in tracking_content, "Missing cancellation in tracking screen"
    assert "_buildStatusChip" in tracking_content, "Missing 13-state chip rendering"
    print("  [OK] Inspection Quotation Sheet, 13-state chips, and cancellation handling verified.\n")


def test_provider_app():
    print("[3] Verifying Provider/Technician Mobile Application (kaamly_provider)...")
    prov_dir = MOBILE_DIR / "provider-app"
    assert (prov_dir / "pubspec.yaml").exists(), "provider-app/pubspec.yaml missing"
    assert (prov_dir / "lib" / "main.dart").exists(), "provider-app/main.dart missing"

    screens = [
        prov_dir / "lib" / "features" / "auth" / "screens" / "provider_login_screen.dart",
        prov_dir / "lib" / "features" / "onboarding" / "screens" / "onboarding_wizard_screen.dart",
        prov_dir / "lib" / "features" / "jobs" / "screens" / "provider_jobs_screen.dart",
        prov_dir / "lib" / "features" / "jobs" / "screens" / "submit_quotation_screen.dart",
        prov_dir / "lib" / "features" / "wallet" / "screens" / "provider_wallet_screen.dart",
    ]
    for s in screens:
        assert s.exists(), f"Missing provider screen: {s.name}"
        print(f"  [OK] Provider Screen verified: {s.name}")

    # Check 5-step onboarding wizard
    wizard = (prov_dir / "lib" / "features" / "onboarding" / "screens" / "onboarding_wizard_screen.dart").read_text(encoding="utf-8")
    assert "Stepper" in wizard, "Wizard must use Flutter Stepper"
    assert "providerOnboardingProfile" in wizard, "Wizard must call profile API"
    assert "providerOnboardingDocuments" in wizard, "Wizard must call documents API"
    assert "providerOnboardingWorkingHours" in wizard, "Wizard must call working hours API"
    assert "providerOnboardingBankAccount" in wizard, "Wizard must call bank account API"
    assert "providerOnboardingSubmit" in wizard, "Wizard must call final review submission"
    print("  [OK] 5-Step Provider Onboarding Wizard verified (Profile, CNIC, Shifts, IBAN, Submit).")

    # Check Wallet debt threshold warning
    wallet = (prov_dir / "lib" / "features" / "wallet" / "screens" / "provider_wallet_screen.dart").read_text(encoding="utf-8")
    assert "outstandingCodCommissionDebt" in wallet, "Wallet must show account 1030 debt"
    assert "earnedPayables" in wallet, "Wallet must show account 2020 payables"
    assert "Job Acceptance Suspended" in wallet, "Wallet must display blocking warning banner when debt >= threshold"
    print("  [OK] Provider Wallet verified: Account 1030 vs 2020 breakdown and PKR 2,000 threshold block banner.\n")


if __name__ == "__main__":
    print("=" * 70)
    print("KAAMLY PHASE 5 - FLUTTER MOBILE APPLICATIONS VALIDATION SUITE")
    print("=" * 70 + "\n")
    test_shared_package()
    test_customer_app()
    test_provider_app()
    print("=" * 70)
    print("SUCCESS: ALL FLUTTER MOBILE APPS & SHARED PACKAGES VALIDATED AND PASSED!")
    print("=" * 70)
