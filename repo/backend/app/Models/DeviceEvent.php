<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceEvent extends Model
{
    protected $fillable = [
        'device_id',
        'event_type',
        'payload',
        'idempotency_key',
        'sequence',
        'status',
        'replay_audit_id',
        'replay_audit_fk',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sequence' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function replayAudit(): BelongsTo
    {
        return $this->belongsTo(DeviceReplayAudit::class, 'replay_audit_fk');
    }
}
