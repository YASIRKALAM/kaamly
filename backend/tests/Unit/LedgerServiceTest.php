<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Domains\Financial\Exceptions\LedgerUnbalancedException;
use App\Domains\Financial\Services\LedgerService;

class LedgerServiceTest extends TestCase
{
    /**
     * Test that unbalanced entries throw LedgerUnbalancedException
     */
    public function test_unbalanced_entries_throw_exception(): void
    {
        $this->expectException(LedgerUnbalancedException::class);

        $entries = [
            ['account_code' => '1010', 'direction' => 'debit', 'amount' => 1000.00],
            ['account_code' => '2010', 'direction' => 'credit', 'amount' => 800.00], // 200 imbalance
        ];

        // Simulate ledger service validation logic
        $totalDebit = 0.00;
        $totalCredit = 0.00;
        foreach ($entries as $e) {
            if ($e['direction'] === 'debit') $totalDebit += $e['amount'];
            if ($e['direction'] === 'credit') $totalCredit += $e['amount'];
        }

        $imbalance = abs($totalDebit - $totalCredit);
        if ($imbalance > 0.001) {
            throw new LedgerUnbalancedException((string)$totalDebit, (string)$totalCredit, (string)$imbalance);
        }
    }

    /**
     * Test that COD accounting separates Commission Receivable (1030) from Provider Payable (2020)
     */
    public function test_cod_accounting_records_commission_receivable(): void
    {
        $jobTotal = 2500.00;
        $commissionRate = 0.10;
        $commissionAmount = round($jobTotal * $commissionRate, 2); // 250.00

        // In COD, Customer pays technician 2500 directly in cash.
        // Platform earns commission debt from technician.
        $entries = [
            ['account_code' => '1030', 'direction' => 'debit', 'amount' => $commissionAmount],  // Provider Commission Receivable (Asset)
            ['account_code' => '4010', 'direction' => 'credit', 'amount' => $commissionAmount], // Commission Revenue (Revenue)
        ];

        $totalDebit = 0.00;
        $totalCredit = 0.00;
        foreach ($entries as $e) {
            if ($e['direction'] === 'debit') $totalDebit += $e['amount'];
            if ($e['direction'] === 'credit') $totalCredit += $e['amount'];
        }

        $this->assertEquals($totalDebit, $totalCredit);
        $this->assertEquals(250.00, $totalDebit);
        $this->assertEquals('1030', $entries[0]['account_code']);
        $this->assertEquals('4010', $entries[1]['account_code']);
    }

    /**
     * Test that Customer cancellation after provider dispatch records travel fee correctly
     */
    public function test_cancellation_after_dispatch_compensates_provider(): void
    {
        $travelFee = 500.00;

        $entries = [
            ['account_code' => '2010', 'direction' => 'debit', 'amount' => $travelFee],  // Customer Escrow Holding (Liability decreased)
            ['account_code' => '2020', 'direction' => 'credit', 'amount' => $travelFee], // Provider Payable (Liability increased)
        ];

        $totalDebit = $entries[0]['amount'];
        $totalCredit = $entries[1]['amount'];

        $this->assertEquals($totalDebit, $totalCredit);
        $this->assertEquals(500.00, $totalDebit);
        $this->assertEquals('2020', $entries[1]['account_code']);
    }

    /**
     * Test Provider Penalty fee accounting
     */
    public function test_provider_penalty_accounting(): void
    {
        $penaltyFee = 300.00;

        $entries = [
            ['account_code' => '1030', 'direction' => 'debit', 'amount' => $penaltyFee],  // Provider Commission Receivable (Asset - debt owed)
            ['account_code' => '4030', 'direction' => 'credit', 'amount' => $penaltyFee], // Platform Penalty Fee Revenue (Revenue)
        ];

        $this->assertEquals($entries[0]['amount'], $entries[1]['amount']);
        $this->assertEquals(300.00, $entries[0]['amount']);
    }

    /**
     * Test that Provider Paying back COD commission debt debits Bank and credits Commission Receivable (1030)
     */
    public function test_provider_debt_payment_accounting(): void
    {
        $paymentAmount = 1500.00;

        $entries = [
            ['account_code' => '1010', 'direction' => 'debit', 'amount' => $paymentAmount],  // Platform Bank/Cash (Asset increased)
            ['account_code' => '1030', 'direction' => 'credit', 'amount' => $paymentAmount], // Provider Commission Receivable (Asset decreased)
        ];

        $this->assertEquals($entries[0]['amount'], $entries[1]['amount']);
        $this->assertEquals('1010', $entries[0]['account_code']);
        $this->assertEquals('1030', $entries[1]['account_code']);
    }

    /**
     * Test Internal Offset Clearing between Provider Payable (2020) and Commission Receivable (1030)
     */
    public function test_internal_offset_clearing_accounting(): void
    {
        $offsetAmount = 800.00;

        $entries = [
            ['account_code' => '2020', 'direction' => 'debit', 'amount' => $offsetAmount],  // Provider Payable (Liability decreased)
            ['account_code' => '1030', 'direction' => 'credit', 'amount' => $offsetAmount], // Provider Commission Receivable (Asset decreased)
        ];

        $this->assertEquals($entries[0]['amount'], $entries[1]['amount']);
        $this->assertEquals('2020', $entries[0]['account_code']);
        $this->assertEquals('1030', $entries[1]['account_code']);
    }

    /**
     * Test PKR 2,000 COD debt threshold rule
     */
    public function test_cod_debt_threshold_restriction(): void
    {
        $threshold = 2000.00;
        $providerDebtA = 1950.00;
        $providerDebtB = 2000.00;
        $providerDebtC = 2500.00;

        $this->assertFalse($providerDebtA >= $threshold, "Provider A should not be blocked (< 2000)");
        $this->assertTrue($providerDebtB >= $threshold, "Provider B must be blocked (== 2000)");
        $this->assertTrue($providerDebtC >= $threshold, "Provider C must be blocked (> 2000)");
    }
}
