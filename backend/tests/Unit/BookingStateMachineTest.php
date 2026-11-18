<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Domains\Booking\Services\BookingStateMachineService;
use App\Domains\Booking\Exceptions\BookingStateTransitionException;

class BookingStateMachineTest extends TestCase
{
    protected BookingStateMachineService $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = new BookingStateMachineService();
    }

    /**
     * Test valid transitions
     */
    public function test_valid_transitions(): void
    {
        // Provider accepts pending broadcast
        $this->assertTrue($this->stateMachine->canTransition('pending', 'accepted', 'provider'));

        // Customer confirms accepted booking
        $this->assertTrue($this->stateMachine->canTransition('accepted', 'confirmed', 'customer'));

        // Provider starts trip
        $this->assertTrue($this->stateMachine->canTransition('confirmed', 'provider_on_the_way', 'provider'));

        // Provider starts work
        $this->assertTrue($this->stateMachine->canTransition('provider_on_the_way', 'in_progress', 'provider'));

        // Provider completes work
        $this->assertTrue($this->stateMachine->canTransition('in_progress', 'completed', 'provider'));

        // Customer opens dispute within window
        $this->assertTrue($this->stateMachine->canTransition('completed', 'disputed', 'customer'));
    }

    /**
     * Test illegal transitions are rejected
     */
    public function test_illegal_transitions_rejected(): void
    {
        // Provider cannot jump directly from pending to completed
        $this->assertFalse($this->stateMachine->canTransition('pending', 'completed', 'provider'));

        // Customer cannot mark booking in_progress
        $this->assertFalse($this->stateMachine->canTransition('provider_on_the_way', 'in_progress', 'customer'));

        // No transitions permitted from terminal state 'cancelled'
        $this->assertFalse($this->stateMachine->canTransition('cancelled', 'confirmed', 'customer'));
        $this->assertFalse($this->stateMachine->canTransition('cancelled', 'in_progress', 'provider'));

        // No transitions permitted from terminal state 'no_show'
        $this->assertFalse($this->stateMachine->canTransition('no_show', 'confirmed', 'customer'));
    }

    /**
     * Test mutual rescheduling transition
     */
    public function test_mutual_reschedule_transition(): void
    {
        // Accepted booking can be rescheduled by customer or provider
        $this->assertTrue($this->stateMachine->canTransition('accepted', 'rescheduled', 'customer'));
        $this->assertTrue($this->stateMachine->canTransition('accepted', 'rescheduled', 'provider'));

        // Rescheduled booking transitions to confirmed after mutual approval
        $this->assertTrue($this->stateMachine->canTransition('rescheduled', 'confirmed', 'customer'));
        $this->assertTrue($this->stateMachine->canTransition('rescheduled', 'confirmed', 'provider'));
    }
}
