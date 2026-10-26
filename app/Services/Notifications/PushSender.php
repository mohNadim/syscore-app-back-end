<?php

namespace App\Services\Notifications;

use App\Models\Notification;

interface PushSender
{
    /**
     * Push the notification to every registered device of its user.
     * Returns true when at least one device received it. Must never throw.
     */
    public function send(Notification $notification): bool;
}
