<?php

use Illuminate\Support\Facades\Broadcast;
use App\Domains\Booking\Models\Booking;
use App\Domains\Communication\Models\ConversationParticipant;
use App\Domains\Provider\Models\ProviderProfile;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

// Private channel for booking updates & live GPS tracking
Broadcast::channel('booking.{id}', function ($user, $id) {
    $booking = Booking::with(['customerProfile', 'providerProfile'])->find($id);
    if (!$booking) {
        return false;
    }

    if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
        return true;
    }

    $isCustomer = $booking->customerProfile && $booking->customerProfile->user_id === $user->id;
    $isProvider = $booking->providerProfile && $booking->providerProfile->user_id === $user->id;

    return $isCustomer || $isProvider;
});

// Private channel for real-time in-app chat
Broadcast::channel('conversation.{id}', function ($user, $id) {
    return ConversationParticipant::where('conversation_id', $id)
        ->where('user_id', $user->id)
        ->exists();
});

// Private channel for provider dispatch alerts
Broadcast::channel('provider.{providerProfileId}', function ($user, $providerProfileId) {
    return ProviderProfile::where('id', $providerProfileId)
        ->where('user_id', $user->id)
        ->exists();
});
