<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class DisplayTimezone
{
    public function __construct(protected SettingStore $settings) {}

    /**
     * Get the configured display time zone name.
     */
    public function name(): string
    {
        return (string) $this->settings->get('app.display_timezone', 'Asia/Damascus');
    }

    /**
     * Convert a local wall-clock datetime (`Y-m-d H:i`) in the display time zone to UTC.
     */
    public function localToUtc(string $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $value, $this->name())->utc();
    }

    /**
     * Convert a moment to the display time zone as an ISO 8601 string.
     */
    public function utcToLocalIso(CarbonInterface $moment): string
    {
        return $moment->copy()->setTimezone($this->name())->toIso8601String();
    }

    /**
     * Get the UTC start of a local calendar day (`Y-m-d`).
     */
    public function dayStartUtc(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $date, $this->name())->startOfDay()->utc();
    }

    /**
     * Get the UTC end of a local calendar day (`Y-m-d`).
     */
    public function dayEndUtc(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $date, $this->name())->endOfDay()->utc();
    }
}
