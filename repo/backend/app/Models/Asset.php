<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'mime',
        'duration_seconds',
        'size_bytes',
        'file_path',
        'fingerprint_sha256',
        'thumbnail_urls',
        'status',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes'       => 'integer',
            'duration_seconds' => 'integer',
            'thumbnail_urls'   => 'array',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'asset_tags',
            'asset_id',
            'tag'
        )->select('tag');
    }

    public function assetTags()
    {
        return $this->hasMany(AssetTag::class, 'asset_id');
    }

    public function playlistItems(): HasMany
    {
        return $this->hasMany(PlaylistItem::class, 'asset_id');
    }

    public function playHistory(): HasMany
    {
        return $this->hasMany(PlayHistory::class, 'asset_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class, 'asset_id');
    }

    public function searchIndex(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SearchIndex::class, 'asset_id');
    }
}
