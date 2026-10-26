<?php

namespace App\Services\Reservations;

use App\Models\OperatingHour;
use App\Services\DisplayTimezone;
use Carbon\CarbonInterface;

class OperatingHoursChecker
{
    public function __construct(protected DisplayTimezone $timezone) {}

    /**
     * Determine whether the interval falls on one local day inside the facility operating hours for that weekday.
     * A weekday without an operating hours row means the facility is closed.
     */
    public function within(int $facilityId, CarbonInterface $startsAt, CarbonInterface $endsAt): bool
    {
        $localStart = $startsAt->copy()->setTimezone($this->timezone->name());
        $localEnd = $endsAt->copy()->setTimezone($this->timezone->name());

        if ($localStart->toDateString() !== $localEnd->toDateString()) {
            return false;
        }

        $hours = OperatingHour::query()
            ->where('facility_id', $facilityId)
            ->where('weekday', $localStart->dayOfWeek)
            ->first();

        return $hours !== null
            && $localStart->format('H:i') >= substr((string) $hours->open_time, 0, 5)
            && $localEnd->format('H:i') <= substr((string) $hours->close_time, 0, 5);
    }
}
