<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Closure;
use App\Models\Facility;
use App\Models\OperatingHour;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\DisplayTimezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBookingFixtures;
use Tests\TestCase;

class AdminCalendarTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    private const URL = '/api/v1/admin/calendar';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createBookingFixtures();
        Sanctum::actingAs($this->staffWithPermissions(['bookings.view']));
    }

    /**
     * @param  list<string>  $codes
     */
    private function staffWithPermissions(array $codes): Staff
    {
        $role = Role::factory()->create(['name_ar' => 'دور '.fake()->unique()->numerify('####'), 'name_en' => 'Role']);

        foreach ($codes as $code) {
            $role->permissions()->attach(Permission::query()->firstOrCreate(['code' => $code], ['description' => $code]));
        }

        return Staff::factory()->create(['role_id' => $role->id]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeBooking(string $localStart, array $attributes = []): Booking
    {
        $startsAt = app(DisplayTimezone::class)->localToUtc($localStart);

        return Booking::factory()->create([
            'unit_id' => $this->fullUnit->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeSession(string $localStart, array $attributes = []): TrainingSession
    {
        $startsAt = app(DisplayTimezone::class)->localToUtc($localStart);

        return TrainingSession::factory()->create([
            'unit_id' => $this->fullUnit->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(90),
            ...$attributes,
        ]);
    }

    public function test_day_view_defaults_to_today_and_lists_units_and_hours(): void
    {
        $this->getJson(self::URL.'?facility_id='.$this->facility->id)->assertOk()
            ->assertJsonPath('data.view', 'day')
            ->assertJsonPath('data.from', '2026-10-26')
            ->assertJsonPath('data.to', '2026-10-26')
            ->assertJsonPath('data.facility_id', $this->facility->id)
            ->assertJsonPath('data.units.*.id', [$this->fullUnit->id, $this->halfUnitA->id, $this->halfUnitB->id])
            ->assertJsonPath('data.days', [['date' => '2026-10-26', 'is_open' => true, 'open_time' => '08:00', 'close_time' => '22:00']])
            ->assertJsonPath('data.closures', [])
            ->assertJsonPath('data.events', []);
    }

    public function test_week_view_runs_sunday_to_saturday_and_marks_closed_days(): void
    {
        OperatingHour::query()->where('facility_id', $this->facility->id)->where('weekday', 5)->delete();

        $response = $this->getJson(self::URL.'?view=week&date=2026-10-28&facility_id='.$this->facility->id)->assertOk()
            ->assertJsonPath('data.from', '2026-10-25')
            ->assertJsonPath('data.to', '2026-10-31')
            ->assertJsonCount(7, 'data.days')
            ->assertJsonPath('data.days.0.date', '2026-10-25')
            ->assertJsonPath('data.days.5', ['date' => '2026-10-30', 'is_open' => false, 'open_time' => null, 'close_time' => null]);

        $this->assertSame('2026-10-31', $response->json('data.days.6.date'));
    }

    public function test_events_mix_bookings_and_sessions_ordered_by_start(): void
    {
        $user = User::factory()->create(['full_name' => 'Ali Hassan', 'phone' => '+963911111111']);
        $booking = $this->makeBooking('2026-10-27 18:00', ['user_id' => $user->id, 'status' => 'confirmed']);
        $guest = Booking::factory()->guest()->create([
            'unit_id' => $this->halfUnitA->id,
            'guest_name' => 'Walk In',
            'guest_phone' => '+963922222222',
            'starts_at' => app(DisplayTimezone::class)->localToUtc('2026-10-27 10:00'),
            'ends_at' => app(DisplayTimezone::class)->localToUtc('2026-10-27 11:00'),
        ]);
        $session = $this->makeSession('2026-10-27 15:00');

        $response = $this->getJson(self::URL.'?date=2026-10-27&facility_id='.$this->facility->id)->assertOk()
            ->assertJsonCount(3, 'data.events');

        $this->assertSame(['booking', 'session', 'booking'], $response->json('data.events.*.type'));
        $this->assertSame([$guest->id, $session->id, $booking->id], $response->json('data.events.*.id'));

        $response->assertJsonPath('data.events.0.customer_name', 'Walk In')
            ->assertJsonPath('data.events.0.phone', '+963922222222')
            ->assertJsonPath('data.events.0.starts_at', '2026-10-27T10:00:00+03:00')
            ->assertJsonPath('data.events.0.unit_id', $this->halfUnitA->id)
            ->assertJsonPath('data.events.0.booking_number', $guest->booking_number)
            ->assertJsonPath('data.events.2.customer_name', 'Ali Hassan')
            ->assertJsonPath('data.events.2.phone', '+963911111111')
            ->assertJsonPath('data.events.2.series_id', null)
            ->assertJsonPath('data.events.1.group.id', $session->group_id)
            ->assertJsonPath('data.events.1.academy.id', $session->group->academy_id)
            ->assertJsonPath('data.events.1.status', 'scheduled');
    }

    public function test_cancelled_and_expired_items_other_facilities_and_other_days_are_excluded(): void
    {
        $this->makeBooking('2026-10-27 10:00', ['status' => 'cancelled']);
        $this->makeBooking('2026-10-27 11:00', ['status' => 'expired']);
        $this->makeBooking('2026-10-28 10:00');
        $this->makeSession('2026-10-27 13:00', ['status' => 'cancelled']);
        Booking::factory()->create(['starts_at' => app(DisplayTimezone::class)->localToUtc('2026-10-27 12:00'), 'ends_at' => app(DisplayTimezone::class)->localToUtc('2026-10-27 13:00')]);
        $kept = [
            $this->makeBooking('2026-10-27 14:00', ['status' => 'pending_deposit'])->id,
            $this->makeBooking('2026-10-27 15:00', ['status' => 'completed'])->id,
            $this->makeBooking('2026-10-27 16:00', ['status' => 'no_show'])->id,
        ];

        $this->getJson(self::URL.'?date=2026-10-27&facility_id='.$this->facility->id)->assertOk()
            ->assertJsonPath('data.events.*.id', $kept);
    }

    public function test_items_overlapping_the_range_edges_are_included(): void
    {
        $edge = $this->makeBooking('2026-10-27 23:30');
        $before = $this->makeBooking('2026-10-26 22:30');

        $this->getJson(self::URL.'?date=2026-10-27&facility_id='.$this->facility->id)->assertOk()
            ->assertJsonPath('data.events.*.id', [$edge->id]);
        $this->assertNotNull($before);
    }

    public function test_closures_of_the_facility_or_its_spaces_are_listed(): void
    {
        $start = app(DisplayTimezone::class)->localToUtc('2026-10-27 12:00');
        $facilityClosure = Closure::factory()->create(['facility_id' => $this->facility->id, 'starts_at' => $start, 'ends_at' => $start->addHours(2), 'reason_ar' => 'صيانة', 'reason_en' => 'Maintenance']);
        $spaceClosure = Closure::factory()->create(['facility_id' => null, 'space_id' => $this->spaceA->id, 'starts_at' => $start->addHours(5), 'ends_at' => $start->addHours(6)]);
        Closure::factory()->create(['facility_id' => Facility::factory()->create()->id, 'starts_at' => $start, 'ends_at' => $start->addHour()]);
        Closure::factory()->create(['facility_id' => $this->facility->id, 'starts_at' => $start->addDays(3), 'ends_at' => $start->addDays(3)->addHour()]);

        $this->getJson(self::URL.'?date=2026-10-27&facility_id='.$this->facility->id)->assertOk()
            ->assertJsonPath('data.closures.*.id', [$facilityClosure->id, $spaceClosure->id])
            ->assertJsonPath('data.closures.0.starts_at', '2026-10-27T12:00:00+03:00')
            ->assertJsonPath('data.closures.0.reason_en', 'Maintenance')
            ->assertJsonPath('data.closures.1.space_id', $this->spaceA->id);
    }

    public function test_the_query_count_does_not_grow_with_the_number_of_events(): void
    {
        $this->makeBooking('2026-10-27 10:00');
        $this->makeSession('2026-10-27 12:00');

        $this->getJson(self::URL.'?view=week&date=2026-10-27&facility_id='.$this->facility->id)->assertOk();

        DB::enableQueryLog();
        $this->getJson(self::URL.'?view=week&date=2026-10-27&facility_id='.$this->facility->id)->assertOk();
        $small = count(DB::getQueryLog());

        foreach (range(1, 8) as $hour) {
            $this->makeBooking('2026-10-28 '.sprintf('%02d', $hour + 8).':00');
            $this->makeSession('2026-10-29 '.sprintf('%02d', $hour + 8).':00');
        }

        DB::flushQueryLog();
        $this->getJson(self::URL.'?view=week&date=2026-10-27&facility_id='.$this->facility->id)->assertOk()->assertJsonCount(18, 'data.events');

        $this->assertSame($small, count(DB::getQueryLog()));
    }

    public function test_validation_in_both_languages(): void
    {
        $this->getJson(self::URL)->assertUnprocessable()
            ->assertJsonPath('errors.facility_id.0', __('admin_calendar.validation.facility_id_invalid', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->getJson(self::URL.'?facility_id=abc&view=month&date=x')->assertUnprocessable()
            ->assertJsonPath('errors.facility_id.0', __('admin_calendar.validation.facility_id_invalid', [], 'en'))
            ->assertJsonPath('errors.view.0', __('admin_calendar.validation.view_invalid', [], 'en'))
            ->assertJsonPath('errors.date.0', __('admin_calendar.validation.date_invalid', [], 'en'));

        $this->getJson(self::URL.'?facility_id=999999')->assertUnprocessable()->assertJsonValidationErrors('facility_id');
    }

    public function test_access_control(): void
    {
        $url = self::URL.'?facility_id='.$this->facility->id;

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->getJson($url)->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson($url)->assertForbidden();

        Sanctum::actingAs($this->staffWithPermissions(['bookings.manage']));
        $this->getJson($url)->assertForbidden();

        Sanctum::actingAs($this->staffWithPermissions(['bookings.view']));
        $this->getJson($url)->assertOk();
    }
}
