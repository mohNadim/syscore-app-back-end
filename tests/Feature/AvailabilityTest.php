<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Closure;
use App\Models\Facility;
use App\Models\OperatingHour;
use App\Models\PriceRule;
use App\Models\SpaceReservation;
use App\Models\Sport;
use App\Models\TrainingSession;
use App\Services\DisplayTimezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesBookingFixtures;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    private const URL = '/api/v1/availability';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createBookingFixtures();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fetch(array $overrides = []): TestResponse
    {
        return $this->getJson(self::URL.'?'.http_build_query([
            'facility_id' => $this->facility->id,
            'date' => '2026-10-27',
            ...$overrides,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function statuses(array $data, int $unitId): array
    {
        $unit = collect($data['units'])->firstWhere('id', $unitId);

        return collect($unit['slots'])->mapWithKeys(fn ($slot) => [substr($slot['starts_at'], 11, 5) => $slot['status']])->all();
    }

    private function reserve(array $spaces, string $localStart, int $minutes = 60): void
    {
        $startsAt = app(DisplayTimezone::class)->localToUtc($localStart);
        $booking = Booking::factory()->create(['starts_at' => $startsAt, 'ends_at' => $startsAt->addMinutes($minutes)]);

        foreach ($spaces as $space) {
            SpaceReservation::factory()->create([
                'space_id' => $space->id,
                'booking_id' => $booking->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes($minutes),
            ]);
        }
    }

    public function test_it_lists_hourly_slots_with_prices_for_every_active_unit(): void
    {
        $response = $this->fetch()->assertOk()
            ->assertJsonPath('data.facility_id', $this->facility->id)
            ->assertJsonPath('data.date', '2026-10-27')
            ->assertJsonPath('data.is_open', true)
            ->assertJsonPath('data.open_time', '08:00')
            ->assertJsonPath('data.close_time', '22:00')
            ->assertJsonPath('data.slot_minutes', 60)
            ->assertJsonPath('data.duration_minutes', 60)
            ->assertJsonCount(3, 'data.units')
            ->assertJsonCount(14, 'data.units.0.slots')
            ->assertJsonPath('data.units.0.id', $this->fullUnit->id)
            ->assertJsonPath('data.units.0.slots.0', [
                'starts_at' => '2026-10-27T08:00:00+03:00',
                'ends_at' => '2026-10-27T09:00:00+03:00',
                'status' => 'available',
                'base_price' => 100000,
            ]);

        $this->assertSame('21:00', substr($response->json('data.units.0.slots.13.starts_at'), 11, 5));
    }

    public function test_longer_duration_stops_when_the_booking_would_pass_closing(): void
    {
        $this->fetch(['duration_minutes' => 120])->assertOk()
            ->assertJsonCount(13, 'data.units.0.slots')
            ->assertJsonPath('data.units.0.slots.0.ends_at', '2026-10-27T10:00:00+03:00')
            ->assertJsonPath('data.units.0.slots.0.base_price', 200000);
    }

    public function test_half_booked_blocks_the_full_unit_and_the_same_half_only(): void
    {
        $this->reserve([$this->spaceA], '2026-10-27 10:00');

        $data = $this->fetch()->assertOk()->json('data');

        $this->assertSame('booked', $this->statuses($data, $this->fullUnit->id)['10:00']);
        $this->assertSame('booked', $this->statuses($data, $this->halfUnitA->id)['10:00']);
        $this->assertSame('available', $this->statuses($data, $this->halfUnitB->id)['10:00']);
        $this->assertSame('available', $this->statuses($data, $this->fullUnit->id)['09:00']);
        $this->assertSame('available', $this->statuses($data, $this->fullUnit->id)['11:00']);
    }

    public function test_full_booked_blocks_both_halves(): void
    {
        $this->reserve([$this->spaceA, $this->spaceB], '2026-10-27 10:00');

        $data = $this->fetch()->assertOk()->json('data');

        $this->assertSame('booked', $this->statuses($data, $this->halfUnitA->id)['10:00']);
        $this->assertSame('booked', $this->statuses($data, $this->halfUnitB->id)['10:00']);
        $this->assertSame('booked', $this->statuses($data, $this->fullUnit->id)['10:00']);
    }

    public function test_inactive_reservations_do_not_block(): void
    {
        $this->reserve([$this->spaceA], '2026-10-27 10:00');
        SpaceReservation::query()->update(['is_active' => false]);

        $data = $this->fetch()->json('data');

        $this->assertSame('available', $this->statuses($data, $this->fullUnit->id)['10:00']);
    }

    public function test_a_training_session_reservation_blocks_the_slot(): void
    {
        $startsAt = app(DisplayTimezone::class)->localToUtc('2026-10-27 16:00');
        $session = TrainingSession::factory()->create([
            'unit_id' => $this->halfUnitB->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(90),
        ]);
        SpaceReservation::factory()->forSession()->create([
            'space_id' => $this->spaceB->id,
            'session_id' => $session->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(90),
        ]);

        $data = $this->fetch()->json('data');

        $this->assertSame('booked', $this->statuses($data, $this->fullUnit->id)['16:00']);
        $this->assertSame('booked', $this->statuses($data, $this->fullUnit->id)['17:00']);
        $this->assertSame('available', $this->statuses($data, $this->halfUnitA->id)['16:00']);
        $this->assertSame('available', $this->statuses($data, $this->fullUnit->id)['18:00']);
    }

    public function test_facility_and_space_closures_close_slots(): void
    {
        $start = app(DisplayTimezone::class)->localToUtc('2026-10-27 12:00');
        Closure::factory()->create(['facility_id' => $this->facility->id, 'starts_at' => $start, 'ends_at' => $start->addHour()]);
        $spaceStart = app(DisplayTimezone::class)->localToUtc('2026-10-27 14:00');
        Closure::factory()->create(['facility_id' => null, 'space_id' => $this->spaceA->id, 'starts_at' => $spaceStart, 'ends_at' => $spaceStart->addHour()]);

        $data = $this->fetch()->json('data');

        foreach ([$this->fullUnit, $this->halfUnitA, $this->halfUnitB] as $unit) {
            $this->assertSame('closed', $this->statuses($data, $unit->id)['12:00']);
        }
        $this->assertSame('closed', $this->statuses($data, $this->fullUnit->id)['14:00']);
        $this->assertSame('closed', $this->statuses($data, $this->halfUnitA->id)['14:00']);
        $this->assertSame('available', $this->statuses($data, $this->halfUnitB->id)['14:00']);
    }

    public function test_slots_that_already_started_are_past(): void
    {
        $data = $this->fetch(['date' => '2026-10-26'])->assertOk()->json('data');
        $statuses = $this->statuses($data, $this->fullUnit->id);

        $this->assertSame('past', $statuses['08:00']);
        $this->assertSame('past', $statuses['11:00']);
        $this->assertSame('available', $statuses['12:00']);
    }

    public function test_past_has_priority_over_booked_and_closed_over_booked(): void
    {
        $this->reserve([$this->spaceA], '2026-10-26 09:00');
        $this->reserve([$this->spaceA], '2026-10-26 15:00');
        $start = app(DisplayTimezone::class)->localToUtc('2026-10-26 15:00');
        Closure::factory()->create(['facility_id' => $this->facility->id, 'starts_at' => $start, 'ends_at' => $start->addHour()]);

        $statuses = $this->statuses($this->fetch(['date' => '2026-10-26'])->json('data'), $this->fullUnit->id);

        $this->assertSame('past', $statuses['09:00']);
        $this->assertSame('closed', $statuses['15:00']);
    }

    public function test_slots_without_price_are_unpriced_and_have_no_base_price(): void
    {
        PriceRule::query()->where('unit_id', $this->halfUnitA->id)->update(['start_time' => '08:00', 'end_time' => '12:00']);

        $data = $this->fetch()->json('data');
        $unit = collect($data['units'])->firstWhere('id', $this->halfUnitA->id);

        $this->assertSame('available', $this->statuses($data, $this->halfUnitA->id)['11:00']);
        $this->assertSame('unpriced', $this->statuses($data, $this->halfUnitA->id)['12:00']);
        $this->assertNull(collect($unit['slots'])->firstWhere('status', 'unpriced')['base_price']);
        $this->assertSame('available', $this->statuses($data, $this->fullUnit->id)['12:00']);
    }

    public function test_peak_and_normal_rules_are_combined(): void
    {
        PriceRule::query()->where('unit_id', $this->fullUnit->id)->delete();
        PriceRule::factory()->create(['unit_id' => $this->fullUnit->id, 'weekday' => null, 'start_time' => '00:00', 'end_time' => '17:00', 'price_per_hour' => 100000, 'valid_from' => '2026-01-01']);
        PriceRule::factory()->peak()->create(['unit_id' => $this->fullUnit->id, 'weekday' => null, 'start_time' => '17:00', 'end_time' => '23:59', 'price_per_hour' => 200000, 'valid_from' => '2026-01-01']);

        $slots = collect($this->fetch(['duration_minutes' => 120])->json('data.units.0.slots'))->keyBy(fn ($slot) => substr($slot['starts_at'], 11, 5));

        $this->assertSame(200000, $slots['10:00']['base_price']);
        $this->assertSame(300000, $slots['16:00']['base_price']);
        $this->assertSame(400000, $slots['18:00']['base_price']);
    }

    public function test_closed_weekday_returns_no_slots(): void
    {
        OperatingHour::query()->where('facility_id', $this->facility->id)->where('weekday', 2)->delete();

        $this->fetch()->assertOk()
            ->assertJsonPath('data.is_open', false)
            ->assertJsonPath('data.open_time', null)
            ->assertJsonCount(3, 'data.units')
            ->assertJsonPath('data.units.0.slots', []);
    }

    public function test_sport_filter_keeps_only_units_offering_the_sport(): void
    {
        $other = Sport::factory()->create();
        $this->halfUnitB->sports()->detach();
        $this->halfUnitB->sports()->attach($other);

        $this->fetch(['sport_id' => $this->sport->id])->assertOk()
            ->assertJsonCount(2, 'data.units')
            ->assertJsonPath('data.units.0.sports.0.id', $this->sport->id);

        $this->fetch(['sport_id' => $other->id])->assertOk()
            ->assertJsonCount(1, 'data.units')
            ->assertJsonPath('data.units.0.id', $this->halfUnitB->id);
    }

    public function test_inactive_units_are_not_listed(): void
    {
        $this->halfUnitA->update(['is_active' => false]);

        $this->fetch()->assertOk()->assertJsonCount(2, 'data.units');
    }

    public function test_date_must_be_inside_the_booking_window(): void
    {
        $this->fetch(['date' => '2026-10-25'])->assertUnprocessable()
            ->assertJsonValidationErrors('date')
            ->assertJsonPath('errors.date.0', __('availability.validation.outside_booking_window', [], 'ar'));

        $this->fetch(['date' => '2026-11-09'])->assertOk();
        $this->fetch(['date' => '2026-11-10'])->assertUnprocessable()->assertJsonValidationErrors('date');
    }

    public function test_validation_messages_in_both_languages(): void
    {
        $this->getJson(self::URL)->assertUnprocessable()
            ->assertJsonPath('errors.facility_id.0', __('availability.validation.facility_id_invalid', [], 'ar'))
            ->assertJsonPath('errors.date.0', __('availability.validation.date_invalid', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')->getJson(self::URL.'?facility_id=abc&date=x&duration_minutes=45&sport_id=zz')
            ->assertUnprocessable()
            ->assertJsonPath('errors.facility_id.0', __('availability.validation.facility_id_invalid', [], 'en'))
            ->assertJsonPath('errors.date.0', __('availability.validation.date_invalid', [], 'en'))
            ->assertJsonPath('errors.sport_id.0', __('availability.validation.sport_id_invalid', [], 'en'));

        $this->fetch(['duration_minutes' => 45])->assertUnprocessable()
            ->assertJsonPath('errors.duration_minutes.0', __('availability.validation.duration_not_multiple', ['minutes' => 60], 'en'));
        $this->fetch(['duration_minutes' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('duration_minutes');
        $this->fetch(['duration_minutes' => 900])->assertUnprocessable()->assertJsonValidationErrors('duration_minutes');
    }

    public function test_inactive_or_unknown_facility_is_rejected(): void
    {
        $inactive = Facility::factory()->create(['is_active' => false]);

        $this->fetch(['facility_id' => $inactive->id])->assertUnprocessable()->assertJsonValidationErrors('facility_id');
        $this->fetch(['facility_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('facility_id');
    }
}
