<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    public $incrementing = false;
    public $timestamps = false;

    protected $primaryKey = 'key';
    protected $keyType = 'string';

    protected $table = 'feature_flags';

    protected $fillable = [
        'key',
        'enabled',
        'reason',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled'    => 'boolean',
            'updated_at' => 'datetime',
        ];
    }
}
