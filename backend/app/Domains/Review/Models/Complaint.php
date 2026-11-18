<?php

namespace App\Domains\Review\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'complaints';

    protected $fillable = [
        'booking_id',
        'complainant_user_id',
        'subject',
        'description',
        'priority',
        'status',
        'resolved_by',
        'resolution_notes',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function complainant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'complainant_user_id', 'id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by', 'id');
    }
}
