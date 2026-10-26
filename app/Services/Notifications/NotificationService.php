<?php

namespace App\Services\Notifications;

use App\Jobs\SendPushNotification;
use App\Models\Notification;
use App\Models\User;

/**
 * Single entry point for creating an in-app notification and queueing its push.
 */
class NotificationService
{
    /**
     * @param  array{ar: string, en: string}  $title
     * @param  array{ar: string, en: string}  $body
     * @param  array<string, mixed>  $data
     */
    public function notify(User $user, string $type, array $title, array $body, array $data = [], ?int $announcementId = null): Notification
    {
        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'title_ar' => $title['ar'],
            'title_en' => $title['en'],
            'body_ar' => $body['ar'],
            'body_en' => $body['en'],
            'data' => $data === [] ? null : $data,
            'announcement_id' => $announcementId,
        ]);

        SendPushNotification::dispatch($notification);

        return $notification;
    }
}
