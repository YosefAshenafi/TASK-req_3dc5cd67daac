<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'tags' => $this->tags ?? [],
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'duration' => $this->duration,
            'status' => $this->status,
            'play_count' => $this->play_count,
            'thumbnail_160' => $this->thumbnail_160,
            'thumbnail_480' => $this->thumbnail_480,
            'thumbnail_960' => $this->thumbnail_960,
            'uploaded_by' => $this->uploaded_by,
            'recommendation_reason' => $this->whenAppended('recommendation_reason', null),
            'recommendation_score' => $this->whenAppended('recommendation_score', null),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
