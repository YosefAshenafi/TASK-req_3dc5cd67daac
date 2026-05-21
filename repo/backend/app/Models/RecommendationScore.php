<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecommendationScore extends Model
{
    protected $table = 'recommendation_scores';

    protected $fillable = [
        'user_id',
        'asset_id',
        'score',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'computed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
