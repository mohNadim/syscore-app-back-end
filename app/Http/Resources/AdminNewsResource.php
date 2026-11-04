<?php

namespace App\Http\Resources;

use App\Models\News;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin News
 */
class AdminNewsResource extends JsonResource
{
    /**
     * Transform the resource into an array; includes unpublished articles and staff-only fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title_ar' => $this->title_ar,
            'title_en' => $this->title_en,
            'body_ar' => $this->body_ar,
            'body_en' => $this->body_en,
            'cover_url' => $this->cover_url,
            'is_published' => $this->is_published,
            'published_at' => $this->published_at ? app(DisplayTimezone::class)->utcToLocalIso($this->published_at) : null,
            'created_by_staff_id' => $this->created_by_staff_id,
            'created_at' => $this->created_at ? app(DisplayTimezone::class)->utcToLocalIso($this->created_at) : null,
        ];
    }
}
