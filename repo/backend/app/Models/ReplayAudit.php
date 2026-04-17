<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplayAudit extends Model
{
    public $timestamps = false;

    protected $table = 'replay_audits';

    protected $fillable = [
        'device_id',
        'initiated_by',
        'since_sequence_no',
        'until_sequence_no',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at'          => 'datetime',
            'since_sequence_no'   => 'integer',
            'until_sequence_no'   => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
