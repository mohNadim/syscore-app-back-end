<?php

namespace App\Services\Bookings;

use App\Exceptions\SeriesUnavailableException;
use App\Models\BookableUnit;
use App\Models\Booking;
use App\Models\BookingSeries;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DisplayTimezone;
use App\Services\Pricing\PricingService;
use App\Services\Reservations\SpaceReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BookingSeriesService
{
    public function __construct(
        protected BookingService $bookings,
        protected SpaceReservationService $reservations,
        protected PricingService $pricing,
        protected DisplayTimezone $timezone,
        protected AuditLogger $audit,
    ) {}

    /**
     * Evaluate every weekly date of a recurring booking without writing anything.
     *
     * `$request` keys: unit, sport_id, start_time (`H:i`), duration_minutes, first_date and last_date (local `Y-m-d`).
     *
     * @param  array{unit: BookableUnit, sport_id: int|null, start_time: string, duration_minutes: int, first_date: string, last_date: string}  $request
     * @return array{dates: list<array<string, mixed>>, totals: array{available_count: int, unavailable_count: int, total_final_price: int}}
     *
     * @throws ValidationException when the unit, sport or duration cannot be booked
     */
    public function preview(array $request, ?User $user): array
    {
        $unit = $this->bookings->prepareUnit($request['unit']);
        $this->bookings->assertBookable($unit, $request['sport_id'], $request['duration_minutes']);

        return $this->summarize($this->evaluate($unit, $request, $user));
    }

    /**
     * Create the series and one booking per available date in a single transaction.
     *
     * Spaces are locked first, so no other writer can take a date between the evaluation and the inserts.
     * `$data` holds the preview keys plus payment_mode and skip_unavailable.
     *
     * @param  array{unit: BookableUnit, sport_id: int|null, start_time: string, duration_minutes: int, first_date: string, last_date: string, payment_mode: string, skip_unavailable: bool}  $data
     * @return array{series: BookingSeries, skipped: list<array{date: string, status: string}>}
     *
     * @throws SeriesUnavailableException when some dates are unavailable and skipping was not requested
     * @throws ConflictHttpException when no date is available
     */
    public function create(array $data, User $user): array
    {
        $unit = $this->bookings->prepareUnit($data['unit']);
        $this->bookings->assertBookable($unit, $data['sport_id'], $data['duration_minutes']);

        return DB::transaction(function () use ($data, $unit, $user): array {
            $this->reservations->lockSpaces($unit->spaces->modelKeys());

            $evaluated = $this->evaluate($unit, $data, $user);
            $available = array_filter($evaluated, fn (array $item): bool => $item['status'] === 'available');
            $skipped = array_values(array_map(
                fn (array $item): array => ['date' => $item['date'], 'status' => $item['status']],
                array_filter($evaluated, fn (array $item): bool => $item['status'] !== 'available'),
            ));

            if ($available === []) {
                throw new ConflictHttpException(__('booking_series.none_available'));
            }

            if ($skipped !== [] && ! $data['skip_unavailable']) {
                throw new SeriesUnavailableException($skipped);
            }

            $series = BookingSeries::create([
                'user_id' => $user->id,
                'unit_id' => $unit->id,
                'sport_id' => $data['sport_id'],
                'weekday' => CarbonImmutable::createFromFormat('Y-m-d', $data['first_date'], $this->timezone->name())->dayOfWeek,
                'start_time' => $data['start_time'],
                'duration_minutes' => $data['duration_minutes'],
                'first_date' => $data['first_date'],
                'last_date' => $data['last_date'],
                'status' => 'active',
            ]);

            foreach ($available as $item) {
                $this->bookings->createWithinLockedTransaction([
                    'unit' => $unit,
                    'sport_id' => $data['sport_id'],
                    'starts_at' => $item['starts_at'],
                    'duration_minutes' => $data['duration_minutes'],
                    'user' => $user,
                    'payment_mode' => $data['payment_mode'],
                    'source' => 'app',
                    'series_id' => $series->id,
                ], $unit, $item['starts_at'], $item['ends_at']);
            }

            $this->audit->log('booking_series.created', $series, null, $series->toArray());

            return ['series' => $series, 'skipped' => $skipped];
        });
    }

    /**
     * Cancel every future booking of the series the user may still cancel, keep the late ones, and close the series.
     *
     * @return array{cancelled_count: int, kept: list<string>}
     *
     * @throws UnprocessableEntityHttpException when the series is not active
     */
    public function cancel(BookingSeries $series, User $user, ?string $reason): array
    {
        return DB::transaction(function () use ($series, $user, $reason): array {
            $locked = BookingSeries::query()->lockForUpdate()->findOrFail($series->id);

            if ($locked->status !== 'active') {
                throw new UnprocessableEntityHttpException(__('booking_series.not_cancellable'));
            }

            $cancelled = 0;
            $kept = [];

            $locked->bookings()
                ->whereIn('status', BookingService::CANCELLABLE_STATUSES)
                ->where('starts_at', '>', now())
                ->orderBy('starts_at')
                ->get()
                ->each(function (Booking $booking) use ($user, $reason, &$cancelled, &$kept): void {
                    if ($this->bookings->cancellationProblem($booking, false) !== null) {
                        $kept[] = $booking->booking_number;

                        return;
                    }

                    $this->bookings->cancel($booking, $user, null, $reason);
                    $cancelled++;
                });

            $locked->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $this->audit->log('booking_series.cancelled', $locked, ['status' => 'active'], ['status' => 'cancelled', 'cancelled_count' => $cancelled, 'kept' => $kept]);

            return ['cancelled_count' => $cancelled, 'kept' => $kept];
        });
    }

    /**
     * Work out the status and price of each weekly date.
     *
     * Statuses: past, outside_hours, closed, unpriced, booked or available. Conflicts are read from the database, so call
     * this after locking the spaces on write paths.
     *
     * @param  array{unit: BookableUnit, sport_id: int|null, start_time: string, duration_minutes: int, first_date: string, last_date: string}  $request
     * @return list<array{date: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, status: string, base_price: int|null, discount_amount: int|null, final_price: int|null}>
     */
    protected function evaluate(BookableUnit $unit, array $request, ?User $user): array
    {
        $zone = $this->timezone->name();
        $last = CarbonImmutable::createFromFormat('Y-m-d', $request['last_date'], $zone)->startOfDay();
        $spaceIds = $unit->spaces->modelKeys();
        $result = [];

        for ($day = CarbonImmutable::createFromFormat('Y-m-d', $request['first_date'], $zone)->startOfDay(); $day->lte($last); $day = $day->addWeek()) {
            $date = $day->toDateString();
            $startsAt = $this->timezone->localToUtc($date.' '.$request['start_time']);
            $endsAt = $startsAt->addMinutes($request['duration_minutes']);

            $status = match ($this->bookings->slotProblem($unit, $startsAt, $endsAt, true)) {
                null => $this->reservations->conflicts($spaceIds, $startsAt, $endsAt)->isNotEmpty() ? 'booked' : 'available',
                'past_start' => 'past',
                'outside_operating_hours' => 'outside_hours',
                'closed' => 'closed',
                default => 'unpriced',
            };

            $quote = $status === 'available' ? $this->pricing->quoteBooking($unit, $startsAt, $endsAt, $user, $request['sport_id']) : null;

            $result[] = [
                'date' => $date,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $status,
                'base_price' => $quote?->basePrice,
                'discount_amount' => $quote?->discountAmount,
                'final_price' => $quote?->finalPrice,
            ];
        }

        return $result;
    }

    /**
     * Shape the evaluated dates for the preview response and add the totals.
     *
     * @param  list<array<string, mixed>>  $evaluated
     * @return array{dates: list<array<string, mixed>>, totals: array{available_count: int, unavailable_count: int, total_final_price: int}}
     */
    protected function summarize(array $evaluated): array
    {
        $available = array_filter($evaluated, fn (array $item): bool => $item['status'] === 'available');

        return [
            'dates' => array_map(fn (array $item): array => [
                'date' => $item['date'],
                'starts_at' => $this->timezone->utcToLocalIso($item['starts_at']),
                'ends_at' => $this->timezone->utcToLocalIso($item['ends_at']),
                'status' => $item['status'],
                'base_price' => $item['base_price'],
                'discount_amount' => $item['discount_amount'],
                'final_price' => $item['final_price'],
            ], $evaluated),
            'totals' => [
                'available_count' => count($available),
                'unavailable_count' => count($evaluated) - count($available),
                'total_final_price' => array_sum(array_column($available, 'final_price')),
            ],
        ];
    }
}
