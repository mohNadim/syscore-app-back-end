<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;

class SendAnnouncement implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(public Announcement $announcement)
    {
        $this->afterCommit();
    }

    /**
     * Notify every recipient in chunks; users who already hold a notification for this announcement are skipped so retries never duplicate.
     */
    public function handle(NotificationService $notifications): void
    {
        $announcement = $this->announcement;

        $title = ['ar' => $announcement->title_ar, 'en' => $announcement->title_en ?? $announcement->title_ar];
        $body = ['ar' => $announcement->body_ar, 'en' => $announcement->body_en ?? $announcement->body_ar];

        $this->recipients($announcement)
            ->whereNotIn('id', Notification::query()->select('user_id')->where('announcement_id', $announcement->id))
            ->chunkById(500, function ($users) use ($notifications, $title, $body, $announcement): void {
                foreach ($users as $user) {
                    $notifications->notify($user, 'announcement', $title, $body, ['announcement_id' => $announcement->id], $announcement->id);
                }
            });
    }

    /**
     * Active users in the announcement audience: everyone for "all"; for "academy", guardians of active children
     * with an active enrollment and an active subscription in one of that academy's groups.
     *
     * @return Builder<User>
     */
    private function recipients(Announcement $announcement): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->when($announcement->audience === 'academy', fn (Builder $query) => $query->whereHas('children', fn (Builder $children) => $children
                ->where('is_active', true)
                ->whereHas('enrollments', fn (Builder $enrollments) => $enrollments
                    ->where('status', 'active')
                    ->whereHas('group', fn (Builder $group) => $group->where('academy_id', $announcement->academy_id))
                    ->whereHas('subscriptions', fn (Builder $subscriptions) => $subscriptions->where('status', 'active')))));
    }
}
