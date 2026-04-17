<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecommendationCandidate extends Model
{
    public $incrementing = false;
    public $timestamps = false;

    protected $primaryKey = null;

    protected $table = 'recommendation_candidates';

    protected $fillable = [
        'user_id',
        'asset_id',
        'score',
        'reason_tags_json',
        'refreshed_at',
    ];

    protected function casts(): array
    {
        return [
            'score'           => 'float',
            'reason_tags_json' => 'array',
            'refreshed_at'    => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}
