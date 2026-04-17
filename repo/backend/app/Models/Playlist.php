<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Playlist extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function playlistItems(): HasMany
    {
        return $this->hasMany(PlaylistItem::class, 'playlist_id')->orderBy('position');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlaylistItem::class, 'playlist_id')->orderBy('position');
    }

    public function playlistShares(): HasMany
    {
        return $this->hasMany(PlaylistShare::class, 'playlist_id');
    }
}
