<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceEventBuffer extends Model
{
    protected $table = 'device_event_buffer';

    protected $fillable = [
        'device_id',
        'event_type',
        'event_payload',
        'idempotency_key',
        'sequence',
        'replay_audit_id',
        'delivery_state',
        'attempt_count',
        'next_retry_at',
        'last_error',
    ];

    protected $casts = [
        'event_payload' => 'array',
        'sequence' => 'integer',
        'attempt_count' => 'integer',
        'next_retry_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
