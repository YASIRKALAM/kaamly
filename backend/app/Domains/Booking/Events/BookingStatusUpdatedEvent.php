<?php

namespace App\Domains\Booking\Events;

use App\Domains\Booking\Models\Booking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingStatusUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Booking $booking;
    public string $previousStatus;
    public string $newStatus;
    public ?string $reason;

    public function __construct(Booking $booking, string $previousStatus, string $newStatus, ?string $reason = null)
    {
        $this->booking = $booking;
        $this->previousStatus = $previousStatus;
        $this->newStatus = $newStatus;
        $this->reason = $reason;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('booking.' . $this->booking->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'booking.status_updated';
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'reason' => $this->reason,
            'updated_at' => now()->toISOString(),
        ];
    }
}
