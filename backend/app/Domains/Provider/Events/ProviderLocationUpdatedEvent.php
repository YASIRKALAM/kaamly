<?php

namespace App\Domains\Provider\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProviderLocationUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $bookingId;
    public string $providerId;
    public float $latitude;
    public float $longitude;

    public function __construct(string $bookingId, string $providerId, float $latitude, float $longitude)
    {
        $this->bookingId = $bookingId;
        $this->providerId = $providerId;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('booking.' . $this->bookingId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'provider.location_updated';
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->bookingId,
            'provider_id' => $this->providerId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timestamp' => now()->toISOString(),
        ];
    }
}
