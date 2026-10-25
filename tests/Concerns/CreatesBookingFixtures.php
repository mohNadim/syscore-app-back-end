<?php

namespace Tests\Concerns;

use App\Models\BookableUnit;
use App\Models\Facility;
use App\Models\OperatingHour;
use App\Models\PriceRule;
use App\Models\Space;
use App\Models\Sport;
use Carbon\CarbonImmutable;

/**
 * A facility with two spaces, a full unit (both spaces) and two half units (one space each),
 * open 08:00-22:00 every day, priced 100000 per hour, with time frozen at Monday 2026-10-26 11:00 Damascus.
 */
trait CreatesBookingFixtures
{
    protected Sport $sport;

    protected Facility $facility;

    protected Space $spaceA;

    protected Space $spaceB;

    protected BookableUnit $fullUnit;

    protected BookableUnit $halfUnitA;

    protected BookableUnit $halfUnitB;

    protected function createBookingFixtures(): void
    {
        $this->withHeader('Accept-Language', 'ar');
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));

        $this->sport = Sport::factory()->create();
        $this->facility = Facility::factory()->create();
        $this->spaceA = Space::factory()->create(['facility_id' => $this->facility->id]);
        $this->spaceB = Space::factory()->create(['facility_id' => $this->facility->id]);
        $this->fullUnit = BookableUnit::factory()->create(['facility_id' => $this->facility->id, 'sort_order' => 1]);
        $this->halfUnitA = BookableUnit::factory()->create(['facility_id' => $this->facility->id, 'sort_order' => 2]);
        $this->halfUnitB = BookableUnit::factory()->create(['facility_id' => $this->facility->id, 'sort_order' => 3]);
        $this->fullUnit->spaces()->attach([$this->spaceA->id, $this->spaceB->id]);
        $this->halfUnitA->spaces()->attach([$this->spaceA->id]);
        $this->halfUnitB->spaces()->attach([$this->spaceB->id]);

        foreach ([$this->fullUnit, $this->halfUnitA, $this->halfUnitB] as $unit) {
            $unit->sports()->attach($this->sport);
            PriceRule::factory()->create([
                'unit_id' => $unit->id,
                'weekday' => null,
                'start_time' => '00:00',
                'end_time' => '23:59',
                'price_per_hour' => 100000,
                'valid_from' => '2026-01-01',
            ]);
        }

        foreach (range(0, 6) as $weekday) {
            OperatingHour::factory()->create([
                'facility_id' => $this->facility->id,
                'weekday' => $weekday,
                'open_time' => '08:00:00',
                'close_time' => '22:00:00',
            ]);
        }
    }
}
