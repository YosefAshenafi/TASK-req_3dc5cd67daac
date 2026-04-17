<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaylistItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'playlist_id',
        'asset_id',
        'position',
    ];

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class, 'playlist_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}
