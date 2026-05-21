<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'tags',
        'mime_type',
        'file_path',
        'file_size',
        'duration',
        'status',
        'uploaded_by',
        'play_count',
        'thumbnail_160',
        'thumbnail_480',
        'thumbnail_960',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'file_size' => 'integer',
            'duration' => 'integer',
            'play_count' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function searchIndex(): HasOne
    {
        return $this->hasOne(AssetSearchIndex::class);
    }

    public function playlistItems(): HasMany
    {
        return $this->hasMany(PlaylistItem::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function playHistory(): HasMany
    {
        return $this->hasMany(PlayHistory::class);
    }

    public function recommendationScores(): HasMany
    {
        return $this->hasMany(RecommendationScore::class);
    }

    public function isReferencedByPlaylist(): bool
    {
        return $this->playlistItems()->exists();
    }
}
