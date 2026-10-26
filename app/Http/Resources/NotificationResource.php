<?php

namespace App\Http\Resources;

use App\Models\Notification;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = app(DisplayTimezone::class);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title_ar' => $this->title_ar,
            'title_en' => $this->title_en,
            'body_ar' => $this->body_ar,
            'body_en' => $this->body_en,
            'data' => $this->data,
            'announcement_id' => $this->announcement_id,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at ? $timezone->utcToLocalIso($this->read_at) : null,
            'created_at' => $this->created_at ? $timezone->utcToLocalIso($this->created_at) : null,
        ];
    }
}
