<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DisplayTimezone;
use App\Services\Payments\ShamCash\ShamCashGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PaymentService
{
    /**
     * Booking statuses that can no longer receive money.
     *
     * @var list<string>
     */
    public const CLOSED_BOOKING_STATUSES = ['cancelled', 'expired', 'no_show', 'completed'];

    public function __construct(
        protected ShamCashGateway $gateway,
        protected DisplayTimezone $timezone,
        protected AuditLogger $audit,
    ) {}

    /**
     * What a booking or subscription currently asks to be paid.
     *
     * @return array{purpose: string, amount: int}
     *
     * @throws UnprocessableEntityHttpException when nothing is payable
     */
    public function payableAmount(Booking|Subscription $target): array
    {
        if ($target instanceof Subscription) {
            if ($target->status === 'pending_payment' && $target->final_price > 0) {
                return ['purpose' => 'subscription', 'amount' => $target->final_price];
            }
        } elseif ($target->status === 'pending_deposit' && $target->payment_mode === 'deposit_then_cash' && $target->deposit_amount > 0) {
            return ['purpose' => 'booking_deposit', 'amount' => $target->deposit_amount];
        } elseif ($target->status === 'pending_deposit' && $target->payment_mode === 'online_full' && $target->final_price > 0) {
            return ['purpose' => 'booking_full', 'amount' => $target->final_price];
        } elseif ($target->status === 'confirmed' && $target->payment_mode === 'deposit_then_cash' && $target->final_price > $target->paid_amount) {
            return ['purpose' => 'booking_balance', 'amount' => $target->final_price - $target->paid_amount];
        }

        throw new UnprocessableEntityHttpException(__('payments.not_payable'));
    }

    /**
     * Start (or return the existing) pending Sham Cash payment for a booking or subscription.
     *
     * The checkout url is kept in `provider_payload['checkout_url']`.
     *
     * @throws UnprocessableEntityHttpException when nothing is payable
     */
    public function initiateShamCash(User $user, Booking|Subscription $target): Payment
    {
        return DB::transaction(function () use ($user, $target): Payment {
            $locked = $target::query()->lockForUpdate()->findOrFail($target->id);
            $due = $this->payableAmount($locked);
            $column = $locked instanceof Booking ? 'booking_id' : 'subscription_id';

            $existing = Payment::query()
                ->where($column, $locked->id)
                ->where('method', 'sham_cash')
                ->where('status', 'pending')
                ->where('purpose', $due['purpose'])
                ->where('amount', $due['amount'])
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $payment = new Payment([
                'user_id' => $user->id,
                'amount' => $due['amount'],
                'method' => 'sham_cash',
                'purpose' => $due['purpose'],
                'status' => 'pending',
                $column => $locked->id,
            ]);

            $checkout = $this->gateway->initiate($payment);
            $payment->provider_reference = $checkout->reference;
            $payment->provider_payload = ['checkout_url' => $checkout->checkoutUrl];
            $this->assignReceiptNumber($payment);
            $payment->save();

            $this->audit->log('payment.initiated', $payment, null, ['purpose' => $payment->purpose, 'amount' => $payment->amount]);

            return $payment;
        });
    }

    /**
     * Apply a signed provider notification; repeated notifications for a settled payment change nothing.
     *
     * @param  array{reference: string, status: string, amount: int, transaction_id?: string|null}  $data
     */
    public function handleShamCashWebhook(array $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            $payment = Payment::query()->where('provider_reference', $data['reference'])->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'pending') {
                return $payment;
            }

            $payment->provider_payload = array_merge($payment->provider_payload ?? [], ['webhook' => $data]);

            if ($data['status'] === 'failed') {
                $payment->status = 'failed';
                $payment->save();
                $this->audit->log('payment.failed', $payment, ['status' => 'pending'], ['status' => 'failed']);

                return $payment;
            }

            if ((int) $data['amount'] !== $payment->amount) {
                $payment->status = 'failed';
                $payment->save();
                $this->audit->log('payment.amount_mismatch', $payment, ['amount' => $payment->amount], ['amount' => (int) $data['amount']]);

                return $payment;
            }

            $payment->status = 'succeeded';
            $payment->paid_at = now();
            $payment->save();

            $this->applySuccessfulPayment($payment);

            return $payment->refresh();
        });
    }

    /**
     * Credit a succeeded payment to its booking or subscription, or refund it when the target cannot take it.
     */
    public function applySuccessfulPayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            if ($payment->booking_id !== null) {
                $this->applyToBooking($payment);
            } else {
                $this->applyToSubscription($payment);
            }
        });
    }

    /**
     * Refund part or all of a succeeded payment.
     *
     * @throws UnprocessableEntityHttpException when the payment is not refundable or the amount is too high
     */
    public function refund(Payment $payment, int $amount, ?Staff $staff, ?string $reason): Refund
    {
        return DB::transaction(function () use ($payment, $amount, $staff, $reason): Refund {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! in_array($locked->status, ['succeeded', 'partially_refunded'], true)) {
                throw new UnprocessableEntityHttpException(__('payments.not_refundable'));
            }

            $refundable = $locked->amount - (int) $locked->refunds()->sum('amount');

            if ($amount <= 0 || $amount > $refundable) {
                throw new UnprocessableEntityHttpException(__('payments.refund_exceeds'));
            }

            $refund = $locked->refunds()->create([
                'amount' => $amount,
                'method' => $locked->method,
                'reason' => $reason,
                'provider_reference' => $locked->method === 'sham_cash' ? $this->gateway->refund($locked, $amount) : null,
                'processed_by_staff_id' => $staff?->id,
            ]);

            $locked->update(['status' => $amount === $refundable ? 'refunded' : 'partially_refunded']);

            if ($locked->booking_id !== null) {
                Booking::query()->whereKey($locked->booking_id)->increment('refund_amount', $amount);
            }

            $this->audit->log('payment.refunded', $locked, null, ['amount' => $amount, 'reason' => $reason], $staff);
            $payment->setRawAttributes($locked->getAttributes(), true);

            return $refund;
        });
    }

    /**
     * Give the payment a unique receipt number: RC + local date (ymd) + 6 random uppercase characters.
     */
    public function assignReceiptNumber(Payment $payment): void
    {
        $prefix = 'RC'.CarbonImmutable::now($this->timezone->name())->format('ymd');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $number = $prefix.strtoupper(Str::random(6));

            if (! Payment::query()->where('receipt_number', $number)->exists()) {
                $payment->receipt_number = $number;

                return;
            }
        }

        throw new ConflictHttpException(__('payments.number_generation_failed'));
    }

    protected function applyToBooking(Payment $payment): void
    {
        $booking = Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);

        if (in_array($booking->status, self::CLOSED_BOOKING_STATUSES, true) || $booking->paid_amount + $payment->amount > $booking->final_price) {
            $this->refund($payment, $payment->amount, null, 'target_not_payable');

            return;
        }

        $before = ['status' => $booking->status, 'paid_amount' => $booking->paid_amount];
        $booking->paid_amount += $payment->amount;

        if ($booking->status === 'pending_deposit') {
            $booking->status = 'confirmed';
        }

        $booking->save();
        $this->audit->log('booking.paid', $booking, $before, ['status' => $booking->status, 'paid_amount' => $booking->paid_amount]);
    }

    protected function applyToSubscription(Payment $payment): void
    {
        $subscription = Subscription::query()->lockForUpdate()->findOrFail($payment->subscription_id);

        if ($subscription->status !== 'pending_payment') {
            $this->refund($payment, $payment->amount, null, 'target_not_payable');

            return;
        }

        $previousEnd = $subscription->renewedFrom?->period_end;
        $start = $previousEnd !== null && $previousEnd->isFuture() ? $previousEnd->copy() : now();

        $subscription->update([
            'status' => 'active',
            'period_start' => $start,
            'period_end' => $start->copy()->addMonth(),
        ]);

        $this->audit->log('subscription.activated', $subscription, ['status' => 'pending_payment'], ['status' => 'active']);
    }
}
