<?php

namespace App\Domains\Booking\Exceptions;

use Exception;

class BookingStateTransitionException extends Exception
{
    protected string $currentStatus;
    protected string $targetStatus;
    protected string $actorRole;

    public function __construct(string $currentStatus, string $targetStatus, string $actorRole, string $message = "")
    {
        $this->currentStatus = $currentStatus;
        $this->targetStatus = $targetStatus;
        $this->actorRole = $actorRole;

        if (empty($message)) {
            $message = "Invalid booking state transition from '{$currentStatus}' to '{$targetStatus}' attempted by '{$actorRole}'.";
        }

        parent::__construct($message, 422);
    }

    public function getCurrentStatus(): string
    {
        return $this->currentStatus;
    }

    public function getTargetStatus(): string
    {
        return $this->targetStatus;
    }

    public function getActorRole(): string
    {
        return $this->actorRole;
    }
}
