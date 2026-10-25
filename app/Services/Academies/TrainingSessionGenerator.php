<?php

namespace App\Services\Academies;

use App\Exceptions\ScheduleConflictException;
use App\Models\GroupSchedule;
use App\Models\TrainingSession;
use App\Services\DisplayTimezone;
use App\Services\Reservations\SpaceReservationService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class TrainingSessionGenerator
{
    public function __construct(
        protected DisplayTimezone $timezone,
        protected SpaceReservationService $reservations,
    ) {}

    /**
     * Create the sessions (and their space reservations) of a schedule for the local dates in the window.
     *
     * `$fromDate` and `$toDate` are read as local calendar dates (only their Y-m-d part counts). The window is
     * clipped to the schedule validity. Existing sessions (matched on starts_at or original_starts_at) are
     * skipped so the call is idempotent, closed dates are skipped and reported, and dates that already
     * started are ignored. Everything runs in one transaction: spaces are locked first, and if any date
     * clashes with an active reservation nothing is persisted.
     *
     * @throws ScheduleConflictException when at least one date overlaps an active reservation on a space of the unit
     */
    public function generate(GroupSchedule $schedule, CarbonInterface $fromDate, CarbonInterface $toDate): GenerationResult
    {
        return DB::transaction(function () use ($schedule, $fromDate, $toDate): GenerationResult {
            $schedule->loadMissing(['unit.spaces', 'group']);

            $zone = $this->timezone->name();
            $spaceIds = $schedule->unit->spaces->modelKeys();
            $facilityId = $schedule->unit->facility_id;

            $from = CarbonImmutable::createFromFormat('Y-m-d', $fromDate->toDateString(), $zone)->startOfDay();
            $to = CarbonImmutable::createFromFormat('Y-m-d', $toDate->toDateString(), $zone)->startOfDay();
            $validFrom = CarbonImmutable::createFromFormat('Y-m-d', $schedule->valid_from->toDateString(), $zone)->startOfDay();

            $start = $from->max($validFrom);
            $end = $schedule->valid_to !== null
                ? $to->min(CarbonImmutable::createFromFormat('Y-m-d', $schedule->valid_to->toDateString(), $zone)->startOfDay())
                : $to;

            $this->reservations->lockSpaces($spaceIds);

            $existing = $this->existingStarts($schedule);
            $now = CarbonImmutable::now();
            $candidates = [];
            $skippedClosed = [];
            $conflicts = [];

            for ($day = $start; $day->lte($end); $day = $day->addDay()) {
                if ($day->dayOfWeek !== $schedule->weekday) {
                    continue;
                }

                $date = $day->toDateString();
                $startsAt = $this->timezone->localToUtc($date.' '.substr((string) $schedule->start_time, 0, 5));
                $endsAt = $this->timezone->localToUtc($date.' '.substr((string) $schedule->end_time, 0, 5));

                if (isset($existing[$startsAt->format('Y-m-d H:i:s')]) || $startsAt->lte($now)) {
                    continue;
                }

                if ($this->reservations->closed($facilityId, $spaceIds, $startsAt, $endsAt)) {
                    $skippedClosed[] = $date;

                    continue;
                }

                if ($this->reservations->conflicts($spaceIds, $startsAt, $endsAt)->isNotEmpty()) {
                    $conflicts[] = [
                        'date' => $date,
                        'starts_at' => $this->timezone->utcToLocalIso($startsAt),
                        'ends_at' => $this->timezone->utcToLocalIso($endsAt),
                    ];

                    continue;
                }

                $candidates[] = [$startsAt, $endsAt];
            }

            if ($conflicts !== []) {
                throw new ScheduleConflictException($conflicts);
            }

            $created = [];

            foreach ($candidates as [$startsAt, $endsAt]) {
                $session = TrainingSession::create([
                    'group_id' => $schedule->group_id,
                    'schedule_id' => $schedule->id,
                    'unit_id' => $schedule->unit_id,
                    'coach_id' => $schedule->group->coach_id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => 'scheduled',
                ]);

                $this->reservations->reserveForSession($session, $spaceIds);
                $created[] = $session;
            }

            return new GenerationResult($created, $skippedClosed);
        });
    }

    /**
     * Get the UTC start moments (Y-m-d H:i:s keys) already used by the schedule's sessions, original or current.
     *
     * @return array<string, true>
     */
    protected function existingStarts(GroupSchedule $schedule): array
    {
        $keys = [];

        TrainingSession::query()
            ->where('schedule_id', $schedule->id)
            ->get(['starts_at', 'original_starts_at'])
            ->each(function (TrainingSession $session) use (&$keys): void {
                $keys[$session->starts_at->format('Y-m-d H:i:s')] = true;

                if ($session->original_starts_at !== null) {
                    $keys[$session->original_starts_at->format('Y-m-d H:i:s')] = true;
                }
            });

        return $keys;
    }
}
