<?php

namespace App\Services\Reports;

use App\Services\DisplayTimezone;
use Carbon\CarbonImmutable;

class ReportPeriod
{
    /**
     * The longest range, in days, a report may cover.
     */
    public const MAX_DAYS = 366;

    public readonly CarbonImmutable $startUtc;

    /**
     * The exclusive end: the UTC start of the local day after the last day.
     */
    public readonly CarbonImmutable $endExclusiveUtc;

    /**
     * @param  string  $from  First local calendar day (`Y-m-d`) in the display time zone.
     * @param  string  $to  Last local calendar day (`Y-m-d`), inclusive.
     */
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        protected DisplayTimezone $timezone,
    ) {
        $this->startUtc = $timezone->dayStartUtc($from);
        $this->endExclusiveUtc = $timezone->dayStartUtc(CarbonImmutable::parse($to)->addDay()->toDateString());
    }

    /**
     * Every local calendar day (`Y-m-d`) in the range.
     *
     * @return list<string>
     */
    public function days(): array
    {
        $days = [];

        for ($day = CarbonImmutable::parse($this->from); $day->toDateString() <= $this->to; $day = $day->addDay()) {
            $days[] = $day->toDateString();
        }

        return $days;
    }

    /**
     * The display time zone offset from UTC, in seconds, at the start of the range.
     *
     * Used to bucket UTC timestamps into local days and months inside SQL.
     */
    public function offsetSeconds(): int
    {
        return $this->startUtc->setTimezone($this->timezone->name())->getOffset();
    }

    /**
     * Convert a local calendar day and wall-clock time to a UTC moment.
     */
    public function localMoment(string $date, string $time): CarbonImmutable
    {
        return $this->timezone->localToUtc($date.' '.substr($time, 0, 5));
    }

    /**
     * The range as it is echoed back in report responses.
     *
     * @return array{date_from: string, date_to: string, timezone: string}
     */
    public function toArray(): array
    {
        return ['date_from' => $this->from, 'date_to' => $this->to, 'timezone' => $this->timezone->name()];
    }
}
