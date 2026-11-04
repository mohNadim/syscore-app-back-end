<?php

namespace App\Services\Notifications;

use App\Jobs\SendDiscountNotification;
use App\Models\Attendance;
use App\Models\Booking;
use App\Models\Child;
use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\DisplayTimezone;
use App\Services\SettingStore;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Turns business events into notifications. A failure here is reported and swallowed so it can never break or roll back the business action.
 */
class NotificationEvents
{
    public function __construct(
        protected NotificationService $notifications,
        protected DisplayTimezone $timezone,
        protected SettingStore $settings,
    ) {}

    /**
     * A booking became confirmed; sent after the surrounding transaction commits.
     */
    public function bookingConfirmed(Booking $booking): void
    {
        $this->afterCommit(function () use ($booking): void {
            $user = $booking->user_id === null ? null : User::query()->find($booking->user_id);

            if ($user === null) {
                return;
            }

            $unit = $booking->unit()->first();

            $this->send($user, 'booking_confirmed', 'booking_confirmed', [
                'booking_number' => $this->pair($booking->booking_number, $booking->booking_number),
                'unit' => $this->pair($unit?->name_ar, $unit?->name_en),
                'time' => $this->pair($this->localTime($booking->starts_at), $this->localTime($booking->starts_at)),
            ], ['booking_id' => $booking->id]);
        });
    }

    /**
     * A payment settled; sent after the surrounding transaction commits.
     */
    public function paymentSucceeded(Payment $payment): void
    {
        $this->afterCommit(function () use ($payment): void {
            $user = $payment->user_id === null ? null : User::query()->find($payment->user_id);

            if ($user === null) {
                return;
            }

            $this->send($user, 'payment_succeeded', 'payment_succeeded', [
                'amount' => $this->pair(number_format($payment->amount), number_format($payment->amount)),
            ], array_filter([
                'payment_id' => $payment->id,
                'booking_id' => $payment->booking_id,
                'subscription_id' => $payment->subscription_id,
            ], fn ($value) => $value !== null));
        });
    }

    /**
     * Remind the booking owner that the booking starts soon; returns whether the notification was created.
     */
    public function bookingReminder(Booking $booking): bool
    {
        $user = $booking->user_id === null ? null : User::query()->find($booking->user_id);

        if ($user === null) {
            return false;
        }

        $unit = $booking->unit()->first();

        return $this->attempt(fn () => $this->send($user, 'booking_reminder', 'booking_reminder', [
            'unit' => $this->pair($unit?->name_ar, $unit?->name_en),
            'time' => $this->pair($this->localTime($booking->starts_at), $this->localTime($booking->starts_at)),
        ], ['booking_id' => $booking->id]));
    }

    /**
     * Tell the guardian their child was marked absent; sent after the surrounding transaction commits.
     */
    public function childAbsent(Attendance $attendance): void
    {
        $this->afterCommit(function () use ($attendance): void {
            $child = $attendance->child()->first();
            $user = $child === null ? null : User::query()->find($child->user_id);

            if ($child === null || $user === null) {
                return;
            }

            $session = $attendance->session()->first();

            $this->send($user, 'child_absent', 'child_absent', [
                'child' => $this->pair($child->full_name, $child->full_name),
                'time' => $this->pair($this->localTime($session?->starts_at), $this->localTime($session?->starts_at)),
            ], ['child_id' => $child->id, 'session_id' => $attendance->session_id]);
        });
    }

    /**
     * Warn the guardian that a subscription ends within a week; returns whether the notification was created.
     */
    public function subscriptionExpiring(Subscription $subscription): bool
    {
        return $this->subscriptionNotice($subscription, 'subscription_expiring');
    }

    /**
     * Tell the guardian a subscription has expired; returns whether the notification was created.
     */
    public function subscriptionExpired(Subscription $subscription): bool
    {
        return $this->subscriptionNotice($subscription, 'subscription_expired');
    }

    /**
     * A session was cancelled; the guardian of each actively enrolled child is told once per child after the surrounding transaction commits.
     */
    public function sessionCancelled(TrainingSession $session): void
    {
        $this->sessionNotice($session, 'session_cancelled');
    }

    /**
     * A session's time or room changed; the guardian of each actively enrolled child is told once per child after the surrounding transaction commits.
     */
    public function sessionUpdated(TrainingSession $session): void
    {
        $this->sessionNotice($session, 'session_updated');
    }

    /**
     * A discount was created; queue the broadcast only when it is active, flagged for notification and currently valid.
     */
    public function discountCreated(Discount $discount): void
    {
        $this->afterCommit(function () use ($discount): void {
            if (SendDiscountNotification::isNotifiable($discount)) {
                SendDiscountNotification::dispatch($discount);
            }
        });
    }

    /**
     * A booking was cancelled by its owner or by staff; the owner is told after the surrounding transaction commits.
     */
    public function bookingCancelled(Booking $booking): void
    {
        $this->bookingNotice($booking, 'booking_cancelled');
    }

    /**
     * An unpaid booking expired; the owner is told after the surrounding transaction commits.
     */
    public function bookingExpired(Booking $booking): void
    {
        $this->bookingNotice($booking, 'booking_expired');
    }

    /**
     * A waitlist seat was offered; the guardian is told after the surrounding transaction commits, with the moment the offer lapses.
     */
    public function waitlistOffered(WaitlistEntry $entry): void
    {
        $this->afterCommit(function () use ($entry): void {
            $expiresAt = $entry->offered_at?->copy()->addHours((int) $this->settings->get('academy.waitlist_offer_hours'));

            $this->waitlistNotice($entry, 'waitlist_offered', [
                'date' => $this->pair($this->localTime($expiresAt), $this->localTime($expiresAt)),
            ], ['offer_expires_at' => $expiresAt === null ? null : $this->timezone->utcToLocalIso($expiresAt)]);
        });
    }

    /**
     * A waitlist offer lapsed unanswered; the guardian is told after the surrounding transaction commits.
     */
    public function waitlistOfferExpired(WaitlistEntry $entry): void
    {
        $this->afterCommit(fn () => $this->waitlistNotice($entry, 'waitlist_offer_expired', [], []));
    }

    /**
     * An enrollment ended; the guardian is told after the surrounding transaction commits.
     */
    public function enrollmentEnded(Enrollment $enrollment): void
    {
        $this->afterCommit(function () use ($enrollment): void {
            $child = $enrollment->child()->with('user')->first();

            if ($child?->user === null) {
                return;
            }

            $group = $enrollment->group()->first();

            $this->send($child->user, 'enrollment_ended', 'enrollment_ended', [
                'child' => $this->pair($child->full_name, $child->full_name),
                'group' => $this->pair($group?->name_ar, $group?->name_en),
            ], ['enrollment_id' => $enrollment->id, 'child_id' => $child->id, 'group_id' => $enrollment->group_id]);
        });
    }

    /**
     * An unpaid subscription was dropped for lack of payment; the guardian is told after the surrounding transaction commits.
     */
    public function subscriptionUnpaidExpired(Subscription $subscription): void
    {
        $this->afterCommit(fn () => $this->subscriptionNotice($subscription, 'subscription_unpaid_expired'));
    }

    protected function bookingNotice(Booking $booking, string $type): void
    {
        $this->afterCommit(function () use ($booking, $type): void {
            $user = $booking->user_id === null ? null : User::query()->find($booking->user_id);

            if ($user === null) {
                return;
            }

            $unit = $booking->unit()->first();

            $this->send($user, $type, $type, [
                'booking_number' => $this->pair($booking->booking_number, $booking->booking_number),
                'unit' => $this->pair($unit?->name_ar, $unit?->name_en),
                'time' => $this->pair($this->localTime($booking->starts_at), $this->localTime($booking->starts_at)),
            ], ['booking_id' => $booking->id]);
        });
    }

    /**
     * @param  array<string, array{ar: string, en: string}>  $extraReplace
     * @param  array<string, mixed>  $extraData
     */
    protected function waitlistNotice(WaitlistEntry $entry, string $type, array $extraReplace, array $extraData): void
    {
        $child = $entry->child()->with('user')->first();

        if ($child?->user === null) {
            return;
        }

        $group = $entry->group()->first();

        $this->send($child->user, $type, $type, [
            'child' => $this->pair($child->full_name, $child->full_name),
            'group' => $this->pair($group?->name_ar, $group?->name_en),
            ...$extraReplace,
        ], [
            'waitlist_entry_id' => $entry->id,
            'group_id' => $entry->group_id,
            'child_id' => $child->id,
            ...$extraData,
        ]);
    }

    protected function subscriptionNotice(Subscription $subscription, string $type): bool
    {
        $enrollment = $subscription->enrollment()->with(['child.user', 'group'])->first();
        $user = $enrollment?->child?->user;

        if ($user === null) {
            return false;
        }

        return $this->attempt(fn () => $this->send($user, $type, $type, [
            'child' => $this->pair($enrollment->child->full_name, $enrollment->child->full_name),
            'group' => $this->pair($enrollment->group?->name_ar, $enrollment->group?->name_en),
            'date' => $this->pair($this->localTime($subscription->period_end), $this->localTime($subscription->period_end)),
        ], ['subscription_id' => $subscription->id, 'child_id' => $enrollment->child_id]));
    }

    protected function sessionNotice(TrainingSession $session, string $type): void
    {
        $this->afterCommit(function () use ($session, $type): void {
            $group = $session->group()->first();
            $unit = $session->unit()->first();

            $replace = [
                'group' => $this->pair($group?->name_ar, $group?->name_en),
                'time' => $this->pair($this->localTime($session->starts_at), $this->localTime($session->starts_at)),
                'unit' => $this->pair($unit?->name_ar, $unit?->name_en),
            ];

            Child::query()
                ->with('user')
                ->whereHas('user', fn (Builder $users) => $users->where('is_active', true))
                ->whereHas('enrollments', fn (Builder $enrollments) => $enrollments
                    ->where('group_id', $session->group_id)
                    ->where('status', 'active'))
                ->each(fn (Child $child) => $this->attempt(fn () => $this->send($child->user, $type, $type, [
                    'child' => $this->pair($child->full_name, $child->full_name),
                    ...$replace,
                ], [
                    'session_id' => $session->id,
                    'group_id' => $session->group_id,
                    'child_id' => $child->id,
                ])));
        });
    }

    /**
     * Create the notification from the `notifications.events.{key}` texts in both languages.
     *
     * @param  array<string, array{ar: string, en: string}>  $replace
     * @param  array<string, mixed>  $data
     */
    protected function send(User $user, string $type, string $key, array $replace, array $data): void
    {
        $texts = [];

        foreach (['ar', 'en'] as $locale) {
            $values = array_map(fn (array $pair): string => $pair[$locale], $replace);

            $texts[$locale] = [
                'title' => __("notifications.events.{$key}.title", $values, $locale),
                'body' => __("notifications.events.{$key}.body", $values, $locale),
            ];
        }

        $this->notifications->notify(
            $user,
            $type,
            ['ar' => $texts['ar']['title'], 'en' => $texts['en']['title']],
            ['ar' => $texts['ar']['body'], 'en' => $texts['en']['body']],
            $data,
        );
    }

    /**
     * Run after the outermost transaction commits (immediately outside one), swallowing and reporting any failure.
     */
    protected function afterCommit(Closure $callback): void
    {
        DB::afterCommit(fn () => $this->attempt($callback));
    }

    protected function attempt(Closure $callback): bool
    {
        try {
            $callback();

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * @return array{ar: string, en: string}
     */
    protected function pair(?string $ar, ?string $en): array
    {
        return ['ar' => $ar ?? $en ?? '', 'en' => $en ?? $ar ?? ''];
    }

    protected function localTime(?DateTimeInterface $moment): string
    {
        return $moment === null ? '' : CarbonImmutable::instance($moment)->setTimezone($this->timezone->name())->format('Y-m-d H:i');
    }
}
