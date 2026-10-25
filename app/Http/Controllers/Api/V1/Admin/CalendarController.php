<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Calendar\ShowCalendarRequest;
use App\Models\Booking;
use App\Models\Closure;
use App\Models\Facility;
use App\Models\OperatingHour;
use App\Models\TrainingSession;
use App\Services\DisplayTimezone;
use Illuminate\Http\JsonResponse;

class CalendarController extends Controller
{
    /**
     * Statuses of bookings that occupy the calendar.
     *
     * @var list<string>
     */
    private const BOOKING_STATUSES = ['pending_deposit', 'confirmed', 'completed', 'no_show'];

    /**
     * Show a facility's day or week: units, opening hours, closures and the bookings and training sessions on its units.
     */
    public function show(ShowCalendarRequest $request, DisplayTimezone $timezone): JsonResponse
    {
        $facility = Facility::query()->findOrFail($request->integer('facility_id'));
        $from = $request->fromDate();
        $to = $request->toDate();
        $rangeStart = $timezone->dayStartUtc($from->toDateString());
        $rangeEnd = $timezone->dayStartUtc($to->toDateString())->addDay();

        $units = $facility->units()->with('spaces:id')->orderBy('sort_order')->orderBy('id')->get();
        $unitIds = $units->modelKeys();
        $spaceIds = $units->pluck('spaces')->flatten()->pluck('id')->unique()->values()->all();
        $hours = OperatingHour::query()->where('facility_id', $facility->id)->get()->keyBy('weekday');

        $days = [];

        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $open = $hours->get($day->dayOfWeek);

            $days[] = [
                'date' => $day->toDateString(),
                'is_open' => $open !== null,
                'open_time' => $open === null ? null : substr((string) $open->open_time, 0, 5),
                'close_time' => $open === null ? null : substr((string) $open->close_time, 0, 5),
            ];
        }

        $closures = Closure::query()
            ->where('starts_at', '<', $rangeEnd)
            ->where('ends_at', '>', $rangeStart)
            ->where(fn ($query) => $query->where('facility_id', $facility->id)->orWhereIn('space_id', $spaceIds))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        $bookings = Booking::query()
            ->with('user:id,full_name,phone')
            ->whereIn('unit_id', $unitIds)
            ->whereIn('status', self::BOOKING_STATUSES)
            ->where('starts_at', '<', $rangeEnd)
            ->where('ends_at', '>', $rangeStart)
            ->get()
            ->map(fn (Booking $booking): array => [
                'type' => 'booking',
                'id' => $booking->id,
                'unit_id' => $booking->unit_id,
                'starts_at' => $timezone->utcToLocalIso($booking->starts_at),
                'ends_at' => $timezone->utcToLocalIso($booking->ends_at),
                'status' => $booking->status,
                'booking_number' => $booking->booking_number,
                'customer_name' => $booking->user?->full_name ?? $booking->guest_name,
                'phone' => $booking->user?->phone ?? $booking->guest_phone,
                'series_id' => $booking->series_id,
                'sort_key' => $booking->starts_at->getTimestamp(),
            ]);

        $sessions = TrainingSession::query()
            ->with('group.academy')
            ->whereIn('unit_id', $unitIds)
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $rangeEnd)
            ->where('ends_at', '>', $rangeStart)
            ->get()
            ->map(fn (TrainingSession $session): array => [
                'type' => 'session',
                'id' => $session->id,
                'unit_id' => $session->unit_id,
                'starts_at' => $timezone->utcToLocalIso($session->starts_at),
                'ends_at' => $timezone->utcToLocalIso($session->ends_at),
                'status' => $session->status,
                'group' => ['id' => $session->group->id, 'name_ar' => $session->group->name_ar, 'name_en' => $session->group->name_en],
                'academy' => ['id' => $session->group->academy->id, 'name_ar' => $session->group->academy->name_ar, 'name_en' => $session->group->academy->name_en],
                'sort_key' => $session->starts_at->getTimestamp(),
            ]);

        $events = $bookings->concat($sessions)
            ->sortBy([['sort_key', 'asc'], ['type', 'asc'], ['id', 'asc']])
            ->map(fn (array $event): array => array_diff_key($event, ['sort_key' => true]))
            ->values();

        return response()->json(['data' => [
            'view' => $request->view(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'facility_id' => $facility->id,
            'units' => $units->map(fn ($unit): array => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name_ar' => $unit->name_ar,
                'name_en' => $unit->name_en,
            ])->all(),
            'days' => $days,
            'closures' => $closures->map(fn (Closure $closure): array => [
                'id' => $closure->id,
                'facility_id' => $closure->facility_id,
                'space_id' => $closure->space_id,
                'starts_at' => $timezone->utcToLocalIso($closure->starts_at),
                'ends_at' => $timezone->utcToLocalIso($closure->ends_at),
                'reason_ar' => $closure->reason_ar,
                'reason_en' => $closure->reason_en,
            ])->all(),
            'events' => $events->all(),
        ]]);
    }
}
