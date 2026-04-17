<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetTag extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $table = 'asset_tags';

    protected $fillable = ['asset_id', 'tag'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}
