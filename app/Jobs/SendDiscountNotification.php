<?php

namespace App\Jobs;

use App\Models\Discount;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendDiscountNotification implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(public Discount $discount)
    {
        $this->afterCommit();
    }

    /**
     * Whether the discount should be announced: active, flagged for notification and valid right now.
     */
    public static function isNotifiable(Discount $discount): bool
    {
        return $discount->is_active
            && $discount->notify_users
            && ($discount->starts_at === null || $discount->starts_at->lte(now()))
            && ($discount->ends_at === null || $discount->ends_at->gt(now()));
    }

    /**
     * Notify every active user in chunks; users who already hold a notification for this discount are skipped so retries never duplicate.
     */
    public function handle(NotificationService $notifications): void
    {
        $discount = $this->discount->refresh();

        if (! self::isNotifiable($discount)) {
            return;
        }

        $name = ['ar' => $discount->name_ar, 'en' => $discount->name_en ?? $discount->name_ar];
        $title = ['ar' => __('notifications.events.discount_created.title', [], 'ar'), 'en' => __('notifications.events.discount_created.title', [], 'en')];
        $body = [
            'ar' => __('notifications.events.discount_created.body', ['name' => $name['ar']], 'ar'),
            'en' => __('notifications.events.discount_created.body', ['name' => $name['en']], 'en'),
        ];

        User::query()
            ->where('is_active', true)
            ->whereNotIn('id', Notification::query()->select('user_id')->where('type', 'discount_created')->where('data->discount_id', $discount->id))
            ->chunkById(500, function ($users) use ($notifications, $title, $body, $discount): void {
                foreach ($users as $user) {
                    $notifications->notify($user, 'discount_created', $title, $body, ['discount_id' => $discount->id]);
                }
            });
    }
}
