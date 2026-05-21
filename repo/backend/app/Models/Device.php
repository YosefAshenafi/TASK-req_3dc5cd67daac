<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = [
        'name',
        'device_type',
        'api_key_hash',
        'last_sequence',
        'last_event_at',
    ];

    protected function casts(): array
    {
        return [
            'last_sequence' => 'integer',
            'last_event_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeviceEvent::class);
    }

    public static function findByApiKey(string $rawKey): ?self
    {
        $hash = hash('sha256', $rawKey);
        return static::where('api_key_hash', $hash)->first();
    }
}
