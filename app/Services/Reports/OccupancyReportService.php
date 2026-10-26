<?php

namespace App\Services\Reports;

use App\Models\BookableUnit;
use App\Models\Closure;
use App\Models\OperatingHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OccupancyReportService
{
    /**
     * Booked hours against available hours per bookable unit and facility.
     *
     * Available time is each facility's operating hours over the range minus the closures that hit the unit
     * (facility-wide closures and closures of one of its spaces). Booked time is summed in SQL, clipped to the range,
     * and skips cancelled and expired bookings.
     *
     * @return array<string, mixed>
     */
    public function generate(ReportPeriod $period, ?int $facilityId = null, ?int $sportId = null): array
    {
        $units = BookableUnit::query()
            ->where('is_active', true)
            ->when($facilityId !== null, fn ($query) => $query->where('facility_id', $facilityId))
            ->when($sportId !== null, fn ($query) => $query->whereHas('sports', fn ($sports) => $sports->where('sports.id', $sportId)))
            ->with(['facility:id,name_ar,name_en', 'spaces:id'])
            ->orderBy('facility_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $facilityIds = $units->pluck('facility_id')->unique()->values()->all();
        $spaceIds = $units->pluck('spaces')->flatten()->pluck('id')->unique()->values()->all();

        $openIntervals = $this->openIntervals($period, $facilityIds);
        $closures = Closure::query()
            ->where('starts_at', '<', $period->endExclusiveUtc)
            ->where('ends_at', '>', $period->startUtc)
            ->where(fn ($query) => $query->whereIn('facility_id', $facilityIds)->orWhereIn('space_id', $spaceIds))
            ->get();
        $bookedSeconds = $this->bookedSeconds($period, $units->modelKeys());

        $unitRows = [];
        $facilityRows = [];

        foreach ($units as $unit) {
            $unitSpaceIds = $unit->spaces->modelKeys();
            $unitClosures = $closures->filter(fn (Closure $closure): bool => $closure->facility_id === $unit->facility_id || in_array($closure->space_id, $unitSpaceIds, true));

            $available = $this->availableSeconds($openIntervals[$unit->facility_id] ?? [], $unitClosures);
            $booked = $bookedSeconds[$unit->id] ?? 0;

            $unitRows[] = [
                'unit_id' => $unit->id,
                'unit_code' => $unit->code,
                'name_ar' => $unit->name_ar,
                'name_en' => $unit->name_en,
                'facility_id' => $unit->facility_id,
                'facility_name_ar' => $unit->facility->name_ar,
                'facility_name_en' => $unit->facility->name_en,
            ] + $this->figures($booked, $available);

            $facilityRows[$unit->facility_id] ??= [
                'facility_id' => $unit->facility_id,
                'name_ar' => $unit->facility->name_ar,
                'name_en' => $unit->facility->name_en,
                'booked' => 0,
                'available' => 0,
            ];
            $facilityRows[$unit->facility_id]['booked'] += $booked;
            $facilityRows[$unit->facility_id]['available'] += $available;
        }

        $facilities = array_map(fn (array $row): array => array_diff_key($row, ['booked' => 0, 'available' => 0]) + $this->figures($row['booked'], $row['available']), array_values($facilityRows));

        return [
            'range' => $period->toArray(),
            'totals' => $this->figures(array_sum(array_column($facilityRows, 'booked')), array_sum(array_column($facilityRows, 'available'))),
            'facilities' => $facilities,
            'units' => $unitRows,
        ];
    }

    /**
     * The sheets of the Excel export.
     *
     * @param  array<string, mixed>  $report
     * @return list<array{name: string, columns: list<string>, rows: list<list<int|float|string|null>>}>
     */
    public function sheets(array $report): array
    {
        $figures = ['booked_hours', 'available_hours', 'occupancy_percent'];

        return [
            ['name' => 'summary', 'columns' => $figures, 'rows' => [array_map(fn (string $key) => $report['totals'][$key], $figures)]],
            ['name' => 'facilities', 'columns' => ['name_ar', 'name_en', ...$figures], 'rows' => array_map(fn (array $row): array => [$row['name_ar'], $row['name_en'], ...array_map(fn (string $key) => $row[$key], $figures)], $report['facilities'])],
            ['name' => 'units', 'columns' => ['facility_name_ar', 'facility_name_en', 'unit_code', 'name_ar', 'name_en', ...$figures], 'rows' => array_map(fn (array $row): array => [$row['facility_name_ar'], $row['facility_name_en'], $row['unit_code'], $row['name_ar'], $row['name_en'], ...array_map(fn (string $key) => $row[$key], $figures)], $report['units'])],
        ];
    }

    /**
     * @return array{booked_hours: float, available_hours: float, occupancy_percent: float}
     */
    protected function figures(int $bookedSeconds, int $availableSeconds): array
    {
        return [
            'booked_hours' => round($bookedSeconds / 3600, 2),
            'available_hours' => round($availableSeconds / 3600, 2),
            'occupancy_percent' => $availableSeconds > 0 ? round($bookedSeconds / $availableSeconds * 100, 2) : 0.0,
        ];
    }

    /**
     * The UTC opening intervals of each facility over the range.
     *
     * @param  list<int>  $facilityIds
     * @return array<int, list<array{0: CarbonImmutable, 1: CarbonImmutable}>>
     */
    protected function openIntervals(ReportPeriod $period, array $facilityIds): array
    {
        $hours = OperatingHour::query()->whereIn('facility_id', $facilityIds)->get()->groupBy('facility_id');
        $days = array_map(fn (string $day): array => [$day, CarbonImmutable::parse($day)->dayOfWeek], $period->days());
        $intervals = [];

        foreach ($hours as $facilityId => $rows) {
            $byWeekday = $rows->groupBy('weekday');
            $intervals[$facilityId] = [];

            foreach ($days as [$day, $weekday]) {
                foreach ($byWeekday->get($weekday, []) as $row) {
                    $start = $period->localMoment($day, (string) $row->open_time);
                    $end = $period->localMoment($day, (string) $row->close_time);

                    if ($end->gt($start)) {
                        $intervals[$facilityId][] = [$start, $end];
                    }
                }
            }
        }

        return $intervals;
    }

    /**
     * Total open seconds after removing the closure time that overlaps them.
     *
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $open
     * @param  Collection<int, Closure>  $closures
     */
    protected function availableSeconds(array $open, Collection $closures): int
    {
        $merged = [];

        foreach ($closures->sortBy('starts_at') as $closure) {
            $start = $closure->starts_at->getTimestamp();
            $end = $closure->ends_at->getTimestamp();
            $last = count($merged) - 1;

            if ($last >= 0 && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        $seconds = 0;

        foreach ($open as [$start, $end]) {
            $length = $end->getTimestamp() - $start->getTimestamp();

            foreach ($merged as [$closedStart, $closedEnd]) {
                $length -= max(0, min($end->getTimestamp(), $closedEnd) - max($start->getTimestamp(), $closedStart));
            }

            $seconds += $length;
        }

        return $seconds;
    }

    /**
     * Booked seconds per unit, clipped to the range, excluding cancelled and expired bookings.
     *
     * @param  list<int|string>  $unitIds
     * @return array<int, int>
     */
    protected function bookedSeconds(ReportPeriod $period, array $unitIds): array
    {
        return DB::table('bookings')
            ->whereIn('unit_id', $unitIds)
            ->whereNotIn('status', ['cancelled', 'expired'])
            ->where('starts_at', '<', $period->endExclusiveUtc)
            ->where('ends_at', '>', $period->startUtc)
            ->selectRaw('unit_id, SUM(TIMESTAMPDIFF(SECOND, GREATEST(starts_at, ?), LEAST(ends_at, ?))) as booked_seconds', [$period->startUtc, $period->endExclusiveUtc])
            ->groupBy('unit_id')
            ->pluck('booked_seconds', 'unit_id')
            ->map(fn ($seconds): int => (int) $seconds)
            ->all();
    }
}
