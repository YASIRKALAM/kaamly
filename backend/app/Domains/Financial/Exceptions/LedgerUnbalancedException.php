<?php

namespace App\Domains\Financial\Exceptions;

use Exception;

class LedgerUnbalancedException extends Exception
{
    protected string $totalDebit;
    protected string $totalCredit;
    protected string $imbalance;

    public function __construct(string $totalDebit, string $totalCredit, string $imbalance, string $message = "")
    {
        $this->totalDebit = $totalDebit;
        $this->totalCredit = $totalCredit;
        $this->imbalance = $imbalance;

        if (empty($message)) {
            $message = "Ledger transaction is unbalanced! Total Debits ($totalDebit) must exactly equal Total Credits ($totalCredit). Imbalance: $imbalance.";
        }

        parent::__construct($message, 422);
    }

    public function getTotalDebit(): string
    {
        return $this->totalDebit;
    }

    public function getTotalCredit(): string
    {
        return $this->totalCredit;
    }

    public function getImbalance(): string
    {
        return $this->imbalance;
    }
}
