<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchIndex extends Model
{
    public $incrementing = false;
    public $timestamps = false;

    protected $primaryKey = 'asset_id';

    protected $table = 'search_index';

    protected $fillable = [
        'asset_id',
        'tokenized_title',
        'tokenized_body',
        'weight_tsv',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}
