<?php

namespace App\Domains\Review\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'reviews';

    protected $fillable = [
        'booking_id',
        'reviewer_user_id',
        'reviewee_user_id',
        'rating',
        'comment',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_public' => 'boolean',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id', 'id');
    }

    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewee_user_id', 'id');
    }
}
