<?php

namespace App\Http\Resources;

use App\Models\Announcement;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Announcement
 */
class AdminAnnouncementResource extends JsonResource
{
    /**
     * Transform the resource into an array; delivery counts appear only when they were loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'audience' => $this->audience,
            'academy_id' => $this->academy_id,
            'user_category_id' => $this->user_category_id,
            'title_ar' => $this->title_ar,
            'title_en' => $this->title_en,
            'body_ar' => $this->body_ar,
            'body_en' => $this->body_en,
            'sent_by_staff_id' => $this->sent_by_staff_id,
            'sent_at' => $this->sent_at ? app(DisplayTimezone::class)->utcToLocalIso($this->sent_at) : null,
            'created_at' => $this->created_at?->toIso8601String(),
            $this->mergeWhen(isset($this->notifications_count), fn () => [
                'recipients_count' => (int) $this->notifications_count,
                'push_sent_count' => (int) $this->push_sent_notifications_count,
            ]),
        ];
    }
}
