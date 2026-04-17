<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'id';
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'kind',
        'label',
        'last_sequence_no',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at'     => 'datetime',
            'last_sequence_no' => 'integer',
        ];
    }

    public function deviceEvents(): HasMany
    {
        return $this->hasMany(DeviceEvent::class, 'device_id');
    }

    public function replayAudits(): HasMany
    {
        return $this->hasMany(ReplayAudit::class, 'device_id');
    }
}
