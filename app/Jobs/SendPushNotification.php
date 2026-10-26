<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\Notifications\PushSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPushNotification implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(public Notification $notification)
    {
        $this->afterCommit();
    }

    /**
     * Push the notification and stamp push_sent_at; skipped when already sent or when no device received it.
     */
    public function handle(PushSender $sender): void
    {
        if ($this->notification->push_sent_at !== null) {
            return;
        }

        if ($sender->send($this->notification)) {
            $this->notification->forceFill(['push_sent_at' => now()])->save();
        }
    }
}
