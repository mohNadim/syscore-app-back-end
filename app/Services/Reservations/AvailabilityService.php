<?php

namespace App\Services\Reservations;

use App\Models\BookableUnit;
use App\Models\Closure;
use App\Models\Facility;
use App\Models\OperatingHour;
use App\Models\PriceRule;
use App\Models\SpaceReservation;
use App\Services\DisplayTimezone;
use App\Services\Pricing\PricingService;
use App\Services\SettingStore;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public function __construct(
        protected DisplayTimezone $timezone,
        protected PricingService $pricing,
        protected SettingStore $settings,
    ) {}

    /**
     * Compute the slot grid of every active unit of the facility for one local day.
     *
     * Everything (units, hours, closures, reservations, price rules) is loaded once; slot statuses are then
     * computed in memory with the priority past, closed, booked or academy_session, unpriced, available.
     *
     * @return array{facility_id: int, date: string, is_open: bool, open_time: string|null, close_time: string|null, slot_minutes: int, duration_minutes: int, units: list<array<string, mixed>>}
     */
    public function forFacilityDay(Facility $facility, CarbonImmutable $localDate, ?int $sportId, int $durationMinutes): array
    {
        $slotMinutes = (int) $this->settings->get('booking.slot_minutes');
        $date = $localDate->toDateString();

        $units = $facility->units()
            ->where('is_active', true)
            ->whereHas('spaces')
            ->when($sportId !== null, fn ($query) => $query->whereHas('sports', fn ($sports) => $sports->where('sports.id', $sportId)))
            ->with(['spaces', 'sports'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $facilitySpaceCount = $facility->spaces()->count();

        $hours = OperatingHour::query()
            ->where('facility_id', $facility->id)
            ->where('weekday', $localDate->dayOfWeek)
            ->first();

        $result = [
            'facility_id' => $facility->id,
            'date' => $date,
            'is_open' => $hours !== null,
            'open_time' => $hours === null ? null : substr((string) $hours->open_time, 0, 5),
            'close_time' => $hours === null ? null : substr((string) $hours->close_time, 0, 5),
            'slot_minutes' => $slotMinutes,
            'duration_minutes' => $durationMinutes,
        ];

        if ($hours === null || $units->isEmpty()) {
            return $result + ['units' => $units->map(fn (BookableUnit $unit): array => $this->unitHeader($unit, $facilitySpaceCount) + ['slots' => []])->all()];
        }

        $dayStart = $this->timezone->dayStartUtc($date);
        $dayEnd = $dayStart->addDay();
        $spaceIds = $units->pluck('spaces')->flatten()->pluck('id')->unique()->values()->all();

        $closures = Closure::query()
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->where(fn ($query) => $query->where('facility_id', $facility->id)->orWhereIn('space_id', $spaceIds))
            ->get();

        $reservations = SpaceReservation::query()
            ->whereIn('space_id', $spaceIds)
            ->where('is_active', true)
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->get();

        $rules = PriceRule::query()
            ->whereIn('unit_id', $units->modelKeys())
            ->where('valid_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>=', $date))
            ->where(fn ($query) => $query->whereNull('weekday')->orWhere('weekday', $localDate->dayOfWeek))
            ->get()
            ->groupBy('unit_id');

        $grid = $this->slotGrid($date, $result['open_time'], $result['close_time'], $slotMinutes, $durationMinutes);
        $now = CarbonImmutable::now();

        $result['units'] = $units->map(function (BookableUnit $unit) use ($facilitySpaceCount, $grid, $closures, $reservations, $rules, $now): array {
            $unitSpaceIds = $unit->spaces->modelKeys();
            $unitRules = $rules->get($unit->id, new Collection);

            $slots = array_map(function (array $slot) use ($unit, $unitSpaceIds, $closures, $reservations, $unitRules, $now): array {
                [$startsAt, $endsAt] = $slot;
                $price = null;

                if ($startsAt->lte($now)) {
                    $status = 'past';
                } elseif ($this->overlapsClosure($closures, $unit->facility_id, $unitSpaceIds, $startsAt, $endsAt)) {
                    $status = 'closed';
                } elseif (($occupant = $this->reservationOccupant($reservations, $unitSpaceIds, $startsAt, $endsAt)) !== null) {
                    $status = $occupant;
                } elseif (($price = $this->pricing->priceFromRules($unitRules, $startsAt, $endsAt)) === null) {
                    $status = 'unpriced';
                } else {
                    $status = 'available';
                }

                return [
                    'starts_at' => $this->timezone->utcToLocalIso($startsAt),
                    'ends_at' => $this->timezone->utcToLocalIso($endsAt),
                    'status' => $status,
                    'base_price' => $price,
                ];
            }, $grid);

            return $this->unitHeader($unit, $facilitySpaceCount) + ['slots' => $slots];
        })->all();

        return $result;
    }

    /**
     * The slot start/end moments (UTC) of a day: every slot_minutes from opening while the booking still ends by closing.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    protected function slotGrid(string $date, string $openTime, string $closeTime, int $slotMinutes, int $durationMinutes): array
    {
        $open = (int) substr($openTime, 0, 2) * 60 + (int) substr($openTime, 3, 2);
        $close = (int) substr($closeTime, 0, 2) * 60 + (int) substr($closeTime, 3, 2);
        $grid = [];

        for ($minute = $open; $minute + $durationMinutes <= $close; $minute += $slotMinutes) {
            $startsAt = $this->timezone->localToUtc($date.' '.sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60));
            $grid[] = [$startsAt, $startsAt->addMinutes($durationMinutes)];
        }

        return $grid;
    }

    /**
     * @param  Collection<int, Closure>  $closures
     * @param  list<int>  $spaceIds
     */
    protected function overlapsClosure(Collection $closures, int $facilityId, array $spaceIds, CarbonInterface $startsAt, CarbonInterface $endsAt): bool
    {
        return $closures->contains(fn (Closure $closure): bool => $closure->starts_at->lt($endsAt)
            && $closure->ends_at->gt($startsAt)
            && ($closure->facility_id === $facilityId || in_array($closure->space_id, $spaceIds, true)));
    }

    /**
     * The slot status caused by overlapping reservations: booked when any real booking overlaps,
     * academy_session when only training sessions do, null when nothing does.
     *
     * @param  Collection<int, SpaceReservation>  $reservations
     * @param  list<int>  $spaceIds
     */
    protected function reservationOccupant(Collection $reservations, array $spaceIds, CarbonInterface $startsAt, CarbonInterface $endsAt): ?string
    {
        $overlapping = $reservations->filter(fn (SpaceReservation $reservation): bool => in_array($reservation->space_id, $spaceIds, true)
            && $reservation->starts_at->lt($endsAt)
            && $reservation->ends_at->gt($startsAt));

        if ($overlapping->isEmpty()) {
            return null;
        }

        return $overlapping->contains(fn (SpaceReservation $reservation): bool => $reservation->session_id === null) ? 'booked' : 'academy_session';
    }

    /**
     * @return array<string, mixed>
     */
    protected function unitHeader(BookableUnit $unit, int $facilitySpaceCount): array
    {
        return [
            'id' => $unit->id,
            'code' => $unit->code,
            'name_ar' => $unit->name_ar,
            'name_en' => $unit->name_en,
            'is_full_unit' => $unit->spaces->count() === $facilitySpaceCount,
            'spaces' => $unit->spaces->map(fn ($space): array => [
                'id' => $space->id,
                'name_ar' => $space->name_ar,
                'name_en' => $space->name_en,
                'code' => $space->code,
            ])->all(),
            'sports' => $unit->sports->map(fn ($sport): array => [
                'id' => $sport->id,
                'name_ar' => $sport->name_ar,
                'name_en' => $sport->name_en,
            ])->all(),
        ];
    }
}
