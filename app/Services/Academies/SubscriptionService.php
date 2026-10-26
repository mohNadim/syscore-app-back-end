<?php

namespace App\Services\Academies;

use App\Models\Enrollment;
use App\Models\Subscription;
use App\Services\AuditLogger;
use App\Services\DisplayTimezone;
use App\Services\Pricing\PricingService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SubscriptionService
{
    public function __construct(
        protected EnrollmentService $enrollments,
        protected PricingService $pricing,
        protected DisplayTimezone $timezone,
        protected AuditLogger $audit,
    ) {}

    /**
     * Ask for the next month: create an unpaid subscription priced now, linked to the one it renews.
     *
     * @throws ConflictHttpException when the enrollment already has a scheduled end or an unpaid renewal
     * @throws UnprocessableEntityHttpException when the subscription cannot be renewed
     */
    public function renew(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription): Subscription {
            $enrollment = Enrollment::query()->lockForUpdate()->findOrFail($subscription->enrollment_id);

            if ($enrollment->status !== 'active') {
                throw new UnprocessableEntityHttpException(__('subscriptions.not_renewable'));
            }

            if ($enrollment->ended_at !== null) {
                throw new ConflictHttpException(__('subscriptions.cancellation_scheduled_conflict'));
            }

            if ($enrollment->subscriptions()->where('status', 'pending_payment')->exists()) {
                throw new ConflictHttpException(__('subscriptions.renewal_pending'));
            }

            $latestId = $enrollment->subscriptions()->max('id');

            if ($subscription->id !== (int) $latestId || ! in_array($subscription->status, ['active', 'expired'], true)) {
                throw new UnprocessableEntityHttpException(__('subscriptions.not_renewable'));
            }

            $quote = $this->pricing->quoteSubscription($enrollment->group, $enrollment->child->user);

            $renewal = $enrollment->subscriptions()->create([
                'status' => 'pending_payment',
                'base_price' => $quote->basePrice,
                'discount_id' => $quote->discount?->id,
                'discount_amount' => $quote->discountAmount,
                'final_price' => $quote->finalPrice,
                'renewed_from_id' => $subscription->id,
            ]);

            $this->audit->log('subscription.renewal_requested', $renewal, null, $renewal->only(['enrollment_id', 'renewed_from_id', 'final_price']));

            return $renewal->refresh();
        });
    }

    /**
     * Cancel a subscription on behalf of its owner: an unpaid one is dropped, a paid month stays valid until its end.
     *
     * @throws ConflictHttpException when the end of the enrollment is already scheduled
     * @throws UnprocessableEntityHttpException when the subscription is expired or already cancelled
     */
    public function cancelByUser(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription): Subscription {
            $enrollment = Enrollment::query()->lockForUpdate()->findOrFail($subscription->enrollment_id);
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);

            if ($locked->status === 'pending_payment') {
                $this->cancelUnpaid($locked, $enrollment);
            } elseif ($locked->status === 'active') {
                $this->scheduleEnd($locked, $enrollment);
            } else {
                throw new UnprocessableEntityHttpException(__('subscriptions.not_cancellable'));
            }

            return $locked->refresh();
        });
    }

    /**
     * Cancel a subscription immediately on behalf of staff: ending the enrollment when it is the running one, dropping it when unpaid.
     *
     * @todo Refund of a paid subscription is handled in phase 8.
     *
     * @throws UnprocessableEntityHttpException when the subscription is expired or already cancelled
     */
    public function cancelNow(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription): Subscription {
            $enrollment = Enrollment::query()->lockForUpdate()->findOrFail($subscription->enrollment_id);
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);

            if ($locked->status === 'pending_payment') {
                $this->cancelUnpaid($locked, $enrollment);
            } elseif ($locked->status === 'active' && $enrollment->status === 'active') {
                $this->enrollments->end($enrollment, null);
            } elseif ($locked->status === 'active') {
                $locked->update(['status' => 'cancelled']);
                $this->audit->logChange('subscription.cancelled', $locked);
            } else {
                throw new UnprocessableEntityHttpException(__('admin_enrollments.subscription_not_cancellable'));
            }

            return $locked->refresh();
        });
    }

    /**
     * Cancel a pending_payment subscription created before the cutoff; the enrollment is cancelled with it when it has nothing active.
     *
     * Returns false when the subscription was paid or is not yet overdue by the time its row is locked.
     */
    public function expireUnpaid(Subscription $subscription, CarbonInterface $createdBefore): bool
    {
        return DB::transaction(function () use ($subscription, $createdBefore): bool {
            $enrollment = Enrollment::query()->lockForUpdate()->findOrFail($subscription->enrollment_id);
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);

            if ($locked->status !== 'pending_payment' || $locked->created_at->gt($createdBefore)) {
                return false;
            }

            $this->cancelUnpaid($locked, $enrollment);

            return true;
        });
    }

    /**
     * Mark an active subscription whose period has ended as expired.
     *
     * Returns false when it is no longer active or its period has not ended by the time its row is locked.
     */
    public function expirePeriod(Subscription $subscription): bool
    {
        return DB::transaction(function () use ($subscription): bool {
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);

            if ($locked->status !== 'active' || $locked->period_end === null || $locked->period_end->gt(now())) {
                return false;
            }

            $locked->update(['status' => 'expired']);
            $this->audit->logChange('subscription.expired', $locked);

            return true;
        });
    }

    /**
     * Cancel an unpaid subscription; when the enrollment then has no active subscription it is cancelled too, freeing the seat.
     */
    protected function cancelUnpaid(Subscription $subscription, Enrollment $enrollment): void
    {
        $subscription->update(['status' => 'cancelled']);
        $this->audit->logChange('subscription.cancelled', $subscription);

        if ($enrollment->status === 'active' && ! $enrollment->subscriptions()->where('status', 'active')->exists()) {
            $enrollment->update(['status' => 'cancelled', 'ended_at' => now()]);
            $this->audit->logChange('enrollment.cancelled', $enrollment);
        }
    }

    /**
     * Keep the paid month and set the enrollment to end when it runs out; unpaid renewals are dropped.
     */
    protected function scheduleEnd(Subscription $subscription, Enrollment $enrollment): void
    {
        if ($enrollment->ended_at !== null) {
            throw new ConflictHttpException(__('subscriptions.already_cancelled'));
        }

        $enrollment->update(['ended_at' => $subscription->period_end]);

        foreach ($enrollment->subscriptions()->where('status', 'pending_payment')->get() as $renewal) {
            $renewal->update(['status' => 'cancelled']);
            $this->audit->logChange('subscription.cancelled', $renewal);
        }

        $this->audit->log('subscription.cancellation_scheduled', $subscription, null, ['enrollment_id' => $enrollment->id, 'ends_at' => $subscription->period_end?->toIso8601String()]);
    }
}
