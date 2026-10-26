<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Fake sender that only writes to the log; the default until FCM is switched on.
 */
class LogPushSender implements PushSender
{
    public function send(Notification $notification): bool
    {
        $deviceCount = $notification->user->devices()->count();

        Log::info('Push notification (fake driver)', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
            'devices' => $deviceCount,
        ]);

        return $deviceCount > 0;
    }
}
