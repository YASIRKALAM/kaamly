<?php

namespace App\Domains\Booking\Events;

use App\Domains\Booking\Models\BookingRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewBookingBroadcastEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $providerProfileId;
    public BookingRequest $bookingRequest;
    public float $distanceKm;

    public function __construct(string $providerProfileId, BookingRequest $bookingRequest, float $distanceKm)
    {
        $this->providerProfileId = $providerProfileId;
        $this->bookingRequest = $bookingRequest;
        $this->distanceKm = $distanceKm;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('provider.' . $this->providerProfileId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'booking.new_broadcast';
    }

    public function broadcastWith(): array
    {
        return [
            'booking_request_id' => $this->bookingRequest->id,
            'service_name' => $this->bookingRequest->service ? $this->bookingRequest->service->name : null,
            'requested_start' => $this->bookingRequest->requested_schedule_start->toISOString(),
            'distance_km' => $this->distanceKm,
            'expires_at' => $this->bookingRequest->expires_at->toISOString(),
        ];
    }
}
