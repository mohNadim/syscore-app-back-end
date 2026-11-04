<?php

namespace App\Http\Resources;

use App\Models\Announcement;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Announcement
 */
class UserAnnouncementResource extends JsonResource
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
            'title_en' => $this->title_en ?? $this->title_ar,
            'body_ar' => $this->body_ar,
            'body_en' => $this->body_en ?? $this->body_ar,
            'sent_at' => $this->sent_at ? app(DisplayTimezone::class)->utcToLocalIso($this->sent_at) : null,
            'created_at' => $this->created_at ? app(DisplayTimezone::class)->utcToLocalIso($this->created_at) : null,
        ];
    }
}
