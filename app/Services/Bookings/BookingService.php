<?php

namespace App\Services\Bookings;

use App\Exceptions\BusinessRuleException;
use App\Models\BookableUnit;
use App\Models\Booking;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DisplayTimezone;
use App\Services\Notifications\NotificationEvents;
use App\Services\Payments\PaymentService;
use App\Services\Pricing\PricingService;
use App\Services\Reservations\OperatingHoursChecker;
use App\Services\Reservations\SpaceReservationService;
use App\Services\SettingStore;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BookingService
{
    /**
     * The longest booking in minutes.
     */
    public const MAX_DURATION_MINUTES = 600;

    /**
     * Statuses of a booking that can still be cancelled.
     *
     * @var list<string>
     */
    public const CANCELLABLE_STATUSES = ['pending_deposit', 'confirmed'];

    public function __construct(
        protected SpaceReservationService $reservations,
        protected OperatingHoursChecker $hours,
        protected PricingService $pricing,
        protected DisplayTimezone $timezone,
        protected SettingStore $settings,
        protected AuditLogger $audit,
        protected PaymentService $payments,
        protected NotificationEvents $events,
    ) {}

    /**
     * Create a booking: validate the request, then lock the unit spaces, check conflicts, price and persist in one transaction.
     *
     * `$data` keys: unit (BookableUnit), sport_id, starts_at (UTC moment), duration_minutes, user (?User) or guest_name + guest_phone,
     * payment_mode and source (`app` or `front_desk`). Staff bookings are exempt from the advance booking window.
     *
     * @param  array{unit: BookableUnit, sport_id: int|null, starts_at: CarbonInterface, duration_minutes: int, user?: User|null, guest_name?: string|null, guest_phone?: string|null, payment_mode: string, source: string}  $data
     *
     * @throws ValidationException when the unit, sport, duration or time cannot be booked
     * @throws ConflictHttpException when a space of the unit is already reserved for part of the interval
     */
    public function create(array $data, ?Staff $staff = null): Booking
    {
        $unit = $this->prepareUnit($data['unit']);
        $startsAt = CarbonImmutable::instance($data['starts_at']);
        $endsAt = $startsAt->addMinutes($data['duration_minutes']);

        $this->assertBookable($unit, $data['sport_id'] ?? null, $data['duration_minutes']);
        $this->assertSlotAllowed($unit, $startsAt, $endsAt, $staff === null && $data['source'] === 'app');

        return DB::transaction(function () use ($data, $unit, $startsAt, $endsAt, $staff): Booking {
            $spaceIds = $unit->spaces->modelKeys();
            $this->reservations->lockSpaces($spaceIds);

            return $this->createWithinLockedTransaction($data, $unit, $startsAt, $endsAt, $staff);
        });
    }

    /**
     * Check conflicts, price, persist and reserve; the caller must already be inside a transaction holding the unit's space locks.
     *
     * @param  array{unit: BookableUnit, sport_id: int|null, starts_at: CarbonInterface, duration_minutes: int, user?: User|null, guest_name?: string|null, guest_phone?: string|null, payment_mode: string, source: string, series_id?: int|null}  $data
     *
     * @throws ConflictHttpException when a space of the unit is already reserved for part of the interval
     * @throws BusinessRuleException when the payment option is disabled (code: payment_option_disabled)
     */
    public function createWithinLockedTransaction(array $data, BookableUnit $unit, CarbonInterface $startsAt, CarbonInterface $endsAt, ?Staff $staff = null): Booking
    {
        $spaceIds = $unit->spaces->modelKeys();

        if ($this->reservations->conflicts($spaceIds, $startsAt, $endsAt)->isNotEmpty()) {
            throw new ConflictHttpException(__('bookings.slot_taken'));
        }

        $isApp = $data['source'] === 'app';

        if ($isApp && $data['payment_mode'] === 'cash_only' && ! (bool) $this->settings->get('booking.cash_only_enabled')) {
            throw new BusinessRuleException('payment_option_disabled', __('bookings.payment_option_disabled'));
        }

        $user = $data['user'] ?? null;
        $quote = $this->pricing->quoteBooking($unit, $startsAt, $endsAt, $user, $data['sport_id'] ?? null);
        $depositPercent = (int) $this->settings->get('booking.deposit_percent');

        $deposit = match (true) {
            $data['payment_mode'] === 'online_full' && $isApp => $quote->finalPrice,
            $data['payment_mode'] === 'deposit_then_cash' => (int) ceil($quote->finalPrice * $depositPercent / 100),
            default => 0,
        };

        $awaitsDeposit = $isApp && $quote->finalPrice > 0 && $data['payment_mode'] !== 'cash_only';

        $booking = new Booking([
            'user_id' => $user?->id,
            'guest_name' => $user === null ? ($data['guest_name'] ?? null) : null,
            'guest_phone' => $user === null ? ($data['guest_phone'] ?? null) : null,
            'unit_id' => $unit->id,
            'sport_id' => $data['sport_id'] ?? null,
            'series_id' => $data['series_id'] ?? null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $awaitsDeposit ? 'pending_deposit' : 'confirmed',
            'source' => $data['source'],
            'payment_mode' => $data['payment_mode'],
            'base_price' => $quote->basePrice,
            'discount_id' => $quote->discount?->id,
            'discount_amount' => $quote->discountAmount,
            'final_price' => $quote->finalPrice,
            'deposit_amount' => $deposit,
            'deposit_due_at' => $awaitsDeposit ? now()->addMinutes((int) $this->settings->get('booking.deposit_deadline_minutes')) : null,
            'paid_amount' => 0,
            'refund_amount' => 0,
            'created_by_staff_id' => $staff?->id,
        ]);

        $this->assignBookingNumber($booking);
        $booking->save();

        $this->reservations->reserveForBooking($booking, $spaceIds);
        $this->audit->log('booking.created', $booking, null, $booking->toArray());

        if ($booking->status === 'confirmed') {
            $this->events->bookingConfirmed($booking);
        }

        return $booking;
    }

    /**
     * Cancel a booking for a customer or for staff.
     *
     * @throws BusinessRuleException when the booking can no longer be cancelled by this actor (codes: not_cancellable, cancellation_deadline_passed)
     */
    public function cancel(Booking $booking, ?User $byUser, ?Staff $byStaff, ?string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $byUser, $byStaff, $reason): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            $problem = $this->cancellationProblem($locked, $byStaff !== null);

            if ($problem !== null) {
                throw new BusinessRuleException($problem, __('bookings.'.$problem));
            }

            $before = ['status' => $locked->status];

            $locked->fill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $byUser?->id,
                'cancelled_by_staff_id' => $byStaff?->id,
                'cancel_reason' => $reason,
            ])->save();

            $this->reservations->releaseForBooking($locked);

            if ($byUser !== null && $byStaff === null) {
                $this->payments->refundOnlinePayments($locked, null, 'customer_cancellation');
                $locked->refresh();
            }

            $this->audit->log('booking.cancelled', $locked, $before, ['status' => 'cancelled', 'cancel_reason' => $reason]);
            $this->events->bookingCancelled($locked);

            return $locked;
        });
    }

    /**
     * Expire a pending_deposit booking whose deposit deadline has passed, releasing its reservations.
     *
     * Returns false when the booking was paid, cancelled or is not yet overdue by the time its row is locked.
     */
    public function expireUnpaid(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking): bool {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($locked->status !== 'pending_deposit' || $locked->deposit_due_at === null || $locked->deposit_due_at->gt(now())) {
                return false;
            }

            $locked->update(['status' => 'expired']);
            $this->reservations->releaseForBooking($locked);
            $this->audit->log('booking.expired', $locked, ['status' => 'pending_deposit'], ['status' => 'expired']);
            $this->events->bookingExpired($locked);

            return true;
        });
    }

    /**
     * Complete a confirmed booking whose end has passed.
     *
     * Returns false when it is no longer confirmed or has not ended by the time its row is locked.
     */
    public function completePast(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking): bool {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($locked->status !== 'confirmed' || $locked->ends_at->gt(now())) {
                return false;
            }

            $locked->update(['status' => 'completed']);
            $this->audit->log('booking.completed', $locked, ['status' => 'confirmed'], ['status' => 'completed']);

            return true;
        });
    }

    /**
     * Mark a confirmed booking whose start has passed as a no-show; its reservations are kept as they are.
     *
     * @throws UnprocessableEntityHttpException when the booking is not confirmed or has not started yet
     */
    public function markNoShow(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($locked->status !== 'confirmed' || $locked->starts_at->gt(now())) {
                throw new UnprocessableEntityHttpException(__('admin_bookings.cannot_mark_no_show'));
            }

            $locked->update(['status' => 'no_show']);
            $this->audit->log('booking.no_show', $locked, ['status' => 'confirmed'], ['status' => 'no_show']);

            return $locked;
        });
    }

    /**
     * The reason a booking cannot be cancelled now as a lang key of `bookings`, or null when it can.
     *
     * Customers may cancel an unpaid booking until it starts and a confirmed one until the cancellation deadline;
     * staff may cancel until the booking ends.
     */
    public function cancellationProblem(Booking $booking, bool $asStaff): ?string
    {
        if (! in_array($booking->status, self::CANCELLABLE_STATUSES, true)) {
            return 'not_cancellable';
        }

        if ($asStaff) {
            return $booking->ends_at->gt(now()) ? null : 'not_cancellable';
        }

        if ($booking->starts_at->lte(now())) {
            return 'not_cancellable';
        }

        if ($booking->status === 'confirmed'
            && now()->gt($booking->starts_at->copy()->subHours((int) $this->settings->get('booking.cancellation_deadline_hours')))) {
            return 'cancellation_deadline_passed';
        }

        return null;
    }

    /**
     * Load what booking needs from the unit once.
     */
    public function prepareUnit(BookableUnit $unit): BookableUnit
    {
        return $unit->loadMissing(['facility', 'spaces', 'sports']);
    }

    /**
     * Check the unit, sport and duration of a booking request.
     *
     * @throws ValidationException
     */
    public function assertBookable(BookableUnit $unit, ?int $sportId, int $durationMinutes): void
    {
        $errors = [];
        $slot = (int) $this->settings->get('booking.slot_minutes');

        if (! $unit->is_active || ! $unit->facility->is_active || $unit->spaces->isEmpty()) {
            $errors['unit_id'] = [__('bookings.validation.unit_unavailable')];
        } elseif ($sportId !== null && ! $unit->sports->contains('id', $sportId)) {
            $errors['sport_id'] = [__('bookings.validation.sport_not_offered')];
        }

        if ($durationMinutes < $slot || $durationMinutes % $slot !== 0) {
            $errors['duration_minutes'] = [__('bookings.validation.duration_not_multiple', ['minutes' => $slot])];
        } elseif ($durationMinutes > self::MAX_DURATION_MINUTES) {
            $errors['duration_minutes'] = [__('bookings.validation.duration_invalid')];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Check that the interval can be booked in principle: not in the past, inside the window (customers only),
     * inside the operating hours, not closed and priced.
     *
     * @throws ValidationException
     */
    public function assertSlotAllowed(BookableUnit $unit, CarbonInterface $startsAt, CarbonInterface $endsAt, bool $enforceWindow): void
    {
        $problem = $this->slotProblem($unit, $startsAt, $endsAt, $enforceWindow);

        if ($problem !== null) {
            throw ValidationException::withMessages(['starts_at' => [
                $problem === 'no_price' ? __('pricing.no_price') : __('bookings.validation.'.$problem),
            ]]);
        }
    }

    /**
     * The first reason the interval cannot be booked, or null: past_start, outside_booking_window, outside_operating_hours, closed or no_price.
     */
    public function slotProblem(BookableUnit $unit, CarbonInterface $startsAt, CarbonInterface $endsAt, bool $enforceWindow): ?string
    {
        if ($startsAt->lte(now())) {
            return 'past_start';
        }

        if ($enforceWindow) {
            $lastDay = CarbonImmutable::now($this->timezone->name())->addDays((int) $this->settings->get('booking.advance_window_days'))->toDateString();

            if ($startsAt->copy()->setTimezone($this->timezone->name())->toDateString() > $lastDay) {
                return 'outside_booking_window';
            }
        }

        if (! $this->hours->within($unit->facility_id, $startsAt, $endsAt)) {
            return 'outside_operating_hours';
        }

        if ($this->reservations->closed($unit->facility_id, $unit->spaces->modelKeys(), $startsAt, $endsAt)) {
            return 'closed';
        }

        try {
            $this->pricing->bookingBasePrice($unit, $startsAt, $endsAt);
        } catch (ValidationException) {
            return 'no_price';
        }

        return null;
    }

    /**
     * Give the booking a unique number: BK + local date (ymd) + 6 random alphanumeric characters.
     */
    protected function assignBookingNumber(Booking $booking): void
    {
        $prefix = 'BK'.CarbonImmutable::now($this->timezone->name())->format('ymd');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $number = $prefix.strtoupper(Str::random(6));

            if (! Booking::query()->where('booking_number', $number)->exists()) {
                $booking->booking_number = $number;

                return;
            }
        }

        throw new ConflictHttpException(__('bookings.number_generation_failed'));
    }
}
