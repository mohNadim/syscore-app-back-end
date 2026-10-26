<?php

namespace App\Http\Resources;

use App\Models\GalleryPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GalleryPhoto
 */
class GalleryPhotoResource extends JsonResource
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
            'album_id' => $this->album_id,
            'image_url' => $this->image_url,
            'caption_ar' => $this->caption_ar,
            'caption_en' => $this->caption_en,
            'sort_order' => $this->sort_order,
        ];
    }
}
