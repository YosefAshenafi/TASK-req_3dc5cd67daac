<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceEvent extends Model
{
    public $timestamps = false;

    protected $table = 'device_events';

    protected $fillable = [
        'device_id',
        'event_type',
        'sequence_no',
        'idempotency_key',
        'occurred_at',
        'received_at',
        'is_out_of_order',
        'payload_json',
    ];

    protected function casts(): array
    {
        return [
            'payload_json'   => 'array',
            'occurred_at'    => 'datetime',
            'received_at'    => 'datetime',
            'is_out_of_order' => 'boolean',
            'sequence_no'    => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
}
