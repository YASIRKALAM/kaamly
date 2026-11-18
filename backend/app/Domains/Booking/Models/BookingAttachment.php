<?php

namespace App\Domains\Booking\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAttachment extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'booking_attachments';

    protected $fillable = [
        'booking_id',
        'file_type',
        'file_path_s3',
        'file_mime_type',
        'file_size_bytes',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'id');
    }
}
