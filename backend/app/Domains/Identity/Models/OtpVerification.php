<?php

namespace App\Domains\Identity\Models;

use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    use HasFactory, HasUuid;

    public $timestamps = false;

    protected $table = 'otp_verifications';

    protected $fillable = [
        'phone_or_email',
        'purpose',
        'otp_hash',
        'attempts',
        'max_attempts',
        'expires_at',
        'verified_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'created_at' => 'datetime',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
        ];
    }
}
