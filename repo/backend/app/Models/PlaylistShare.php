<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaylistShare extends Model
{
    protected $fillable = [
        'playlist_id',
        'code',
        'created_by',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'  => 'datetime',
            'revoked_at'  => 'datetime',
        ];
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class, 'playlist_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
