<?php

namespace Database\Seeders;

use App\Models\BookableUnit;
use App\Models\Facility;
use App\Models\OperatingHour;
use App\Models\PriceRule;
use App\Models\Sport;
use Illuminate\Database\Seeder;

class DemoBookingDataSeeder extends Seeder
{
    /**
     * Off-peak and peak hourly prices per unit code.
     *
     * @var array<string, array{off_peak: int, peak: int}>
     */
    private const PRICES = [
        'PITCH_FULL' => ['off_peak' => 50000, 'peak' => 75000],
        'PITCH_A' => ['off_peak' => 30000, 'peak' => 45000],
        'PITCH_B' => ['off_peak' => 30000, 'peak' => 45000],
        'HALL' => ['off_peak' => 40000, 'peak' => 60000],
    ];

    /**
     * Seed sports, opening hours and price rules so bookings can be made against the API locally (local environment only).
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $football = Sport::firstOrCreate(['name_en' => 'Football'], ['name_ar' => 'كرة القدم', 'is_active' => true]);
        $basketball = Sport::firstOrCreate(['name_en' => 'Basketball'], ['name_ar' => 'كرة السلة', 'is_active' => true]);

        foreach (Facility::all() as $facility) {
            foreach (range(0, 6) as $weekday) {
                OperatingHour::updateOrCreate(
                    ['facility_id' => $facility->id, 'weekday' => $weekday],
                    ['open_time' => '08:00:00', 'close_time' => '23:00:00'],
                );
            }
        }

        foreach (BookableUnit::query()->whereIn('code', array_keys(self::PRICES))->get() as $unit) {
            $unit->sports()->syncWithoutDetaching($unit->code === 'HALL' ? [$basketball->id] : [$football->id]);

            $this->priceRule($unit, '08:00:00', '17:00:00', false, self::PRICES[$unit->code]['off_peak']);
            $this->priceRule($unit, '17:00:00', '23:00:00', true, self::PRICES[$unit->code]['peak']);
        }
    }

    /**
     * Create an every-day price rule for the unit unless one already covers the same hours.
     */
    private function priceRule(BookableUnit $unit, string $startTime, string $endTime, bool $isPeak, int $pricePerHour): void
    {
        PriceRule::firstOrCreate(
            ['unit_id' => $unit->id, 'weekday' => null, 'start_time' => $startTime, 'end_time' => $endTime],
            ['is_peak' => $isPeak, 'price_per_hour' => $pricePerHour, 'valid_from' => '2026-01-01', 'valid_to' => null],
        );
    }
}
