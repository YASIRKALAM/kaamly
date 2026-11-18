<?php

namespace App\Domains\Financial\Models;

use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    use HasFactory, HasUuid;

    public $timestamps = false;

    protected $table = 'payment_webhook_events';

    protected $fillable = [
        'gateway_name',
        'event_type',
        'event_id',
        'raw_payload',
        'processed_at',
        'status',
        'failure_message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
