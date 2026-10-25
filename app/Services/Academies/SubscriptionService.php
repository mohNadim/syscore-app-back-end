<?php

namespace App\Services\Academies;

use App\Models\Enrollment;
use App\Models\Subscription;
use App\Services\AuditLogger;
use App\Services\DisplayTimezone;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SubscriptionService
{
    public function __construct(
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
     *
     * @todo The phase 11 job ends the enrollment once its scheduled end passes.
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
