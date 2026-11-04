<?php

namespace App\Http\Resources;

use App\Models\GalleryAlbum;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GalleryAlbum
 */
class GalleryAlbumResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title_ar' => $this->title_ar,
            'title_en' => $this->title_en,
            'sort_order' => $this->sort_order,
            'is_published' => $this->is_published,
            'photos_count' => $this->whenCounted('photos'),
            'cover_url' => $this->coverUrl(),
            'photos' => GalleryPhotoResource::collection($this->whenLoaded('photos')),
            'created_at' => $this->created_at ? app(DisplayTimezone::class)->utcToLocalIso($this->created_at) : null,
        ];
    }

    /**
     * Get the cover image URL: the first photo by sort order, or null when none is loaded or present.
     */
    private function coverUrl(): ?string
    {
        if ($this->resource->relationLoaded('coverPhoto')) {
            return $this->coverPhoto?->image_url;
        }

        return $this->resource->relationLoaded('photos') ? $this->photos->first()?->image_url : null;
    }
}
