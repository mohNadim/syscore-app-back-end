<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingSeries;
use App\Models\Closure;
use App\Models\Discount;
use App\Models\PriceRule;
use App\Models\SpaceReservation;
use App\Models\Staff;
use App\Models\User;
use App\Models\UserCategory;
use App\Services\DisplayTimezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBookingFixtures;
use Tests\TestCase;

class BookingSeriesTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    private const URL = '/api/v1/booking-series';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createBookingFixtures();
        $this->user = Sanctum::actingAs(User::factory()->create());
    }

    /**
     * Monday 18:00 every week from 2026-10-26 to 2026-11-09: three dates.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'unit_id' => $this->fullUnit->id,
            'sport_id' => $this->sport->id,
            'start_time' => '18:00',
            'duration_minutes' => 60,
            'first_date' => '2026-10-26',
            'last_date' => '2026-11-09',
            'payment_mode' => 'deposit_then_cash',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function preview(array $overrides = []): TestResponse
    {
        return $this->postJson(self::URL.'/preview', $this->payload($overrides));
    }

    private function blockHalf(string $localStart, int $minutes = 60): void
    {
        $startsAt = app(DisplayTimezone::class)->localToUtc($localStart);
        $booking = Booking::factory()->create(['starts_at' => $startsAt, 'ends_at' => $startsAt->addMinutes($minutes)]);
        SpaceReservation::factory()->create(['space_id' => $this->spaceA->id, 'booking_id' => $booking->id, 'starts_at' => $startsAt, 'ends_at' => $startsAt->addMinutes($minutes)]);
    }

    public function test_preview_lists_every_weekly_date_with_prices_and_totals_and_writes_nothing(): void
    {
        $this->blockHalf('2026-11-02 18:00');
        $closureStart = app(DisplayTimezone::class)->localToUtc('2026-11-09 17:00');
        Closure::factory()->create(['facility_id' => $this->facility->id, 'starts_at' => $closureStart, 'ends_at' => $closureStart->addHours(3)]);
        $bookings = Booking::query()->count();

        $this->preview()->assertOk()
            ->assertJsonPath('data.dates.0', [
                'date' => '2026-10-26',
                'starts_at' => '2026-10-26T18:00:00+03:00',
                'ends_at' => '2026-10-26T19:00:00+03:00',
                'status' => 'available',
                'base_price' => 100000,
                'discount_amount' => 0,
                'final_price' => 100000,
            ])
            ->assertJsonPath('data.dates.1.status', 'booked')
            ->assertJsonPath('data.dates.1.final_price', null)
            ->assertJsonPath('data.dates.2.status', 'closed')
            ->assertJsonPath('data.totals', ['available_count' => 1, 'unavailable_count' => 2, 'total_final_price' => 100000]);

        $this->assertSame($bookings, Booking::query()->count());
        $this->assertSame(0, BookingSeries::query()->count());
    }

    public function test_preview_marks_past_outside_hours_and_unpriced_dates(): void
    {
        $this->preview(['start_time' => '09:00'])->assertOk()
            ->assertJsonPath('data.dates.0.status', 'past')
            ->assertJsonPath('data.dates.1.status', 'available');

        $this->preview(['start_time' => '21:30'])->assertOk()
            ->assertJsonPath('data.dates.0.status', 'outside_hours')
            ->assertJsonPath('data.totals.available_count', 0);

        PriceRule::query()->where('unit_id', $this->fullUnit->id)->update(['start_time' => '08:00', 'end_time' => '12:00']);
        $this->preview()->assertOk()->assertJsonPath('data.dates.0.status', 'unpriced');
    }

    public function test_preview_applies_the_members_discount(): void
    {
        $category = UserCategory::factory()->create();
        $this->user->categories()->attach($category);
        Discount::factory()->create(['type' => 'percent', 'value' => 10, 'target' => 'booking'])->categories()->attach($category);

        $this->preview()->assertOk()
            ->assertJsonPath('data.dates.0.discount_amount', 10000)
            ->assertJsonPath('data.dates.0.final_price', 90000)
            ->assertJsonPath('data.totals.total_final_price', 270000);
    }

    public function test_store_creates_the_series_and_one_booking_per_date(): void
    {
        $this->postJson(self::URL, $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', __('booking_series.created', [], 'ar'))
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.weekday', 1)
            ->assertJsonPath('data.start_time', '18:00')
            ->assertJsonPath('data.first_date', '2026-10-26')
            ->assertJsonPath('data.last_date', '2026-11-09')
            ->assertJsonCount(3, 'data.bookings')
            ->assertJsonPath('data.bookings.0.starts_at', '2026-10-26T18:00:00+03:00')
            ->assertJsonPath('data.bookings.0.status', 'pending_deposit')
            ->assertJsonPath('data.bookings.0.deposit_amount', 30000)
            ->assertJsonPath('skipped', []);

        $series = BookingSeries::query()->sole();
        $this->assertSame(3, Booking::query()->where('series_id', $series->id)->where('user_id', $this->user->id)->count());
        $this->assertSame(6, SpaceReservation::query()->where('is_active', true)->count());
        $this->assertTrue(AuditLog::query()->where('action', 'booking_series.created')->where('entity_id', $series->id)->exists());
        $this->assertSame(3, AuditLog::query()->where('action', 'booking.created')->count());
    }

    public function test_unavailable_dates_roll_everything_back_with_the_list(): void
    {
        $this->blockHalf('2026-11-02 18:00');
        $before = Booking::query()->count();

        $this->postJson(self::URL, $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('message', __('booking_series.some_unavailable', [], 'ar'))
            ->assertExactJsonStructure(['message', 'unavailable' => [['date', 'status']]])
            ->assertJsonPath('unavailable', [['date' => '2026-11-02', 'status' => 'booked']]);

        $this->assertSame($before, Booking::query()->count());
        $this->assertSame(0, BookingSeries::query()->count());
    }

    public function test_skip_unavailable_creates_only_the_available_dates(): void
    {
        $this->blockHalf('2026-11-02 18:00');

        $this->postJson(self::URL, $this->payload(['skip_unavailable' => true]))
            ->assertCreated()
            ->assertJsonCount(2, 'data.bookings')
            ->assertJsonPath('skipped', [['date' => '2026-11-02', 'status' => 'booked']]);

        $series = BookingSeries::query()->sole();
        $this->assertSame(['2026-10-26', '2026-11-09'], $series->bookings()->orderBy('starts_at')->get()
            ->map(fn (Booking $booking) => $booking->starts_at->copy()->setTimezone('Asia/Damascus')->toDateString())->all());
    }

    public function test_no_available_date_is_a_conflict_even_when_skipping(): void
    {
        $this->postJson(self::URL, $this->payload(['start_time' => '21:30', 'skip_unavailable' => true]))
            ->assertStatus(409)
            ->assertJsonPath('message', __('booking_series.none_available', [], 'ar'));

        $this->assertSame(0, BookingSeries::query()->count());
    }

    public function test_series_cannot_take_a_slot_that_an_overlapping_booking_holds_on_a_half(): void
    {
        $this->blockHalf('2026-10-26 18:00');

        $this->postJson(self::URL, $this->payload(['unit_id' => $this->halfUnitB->id]))->assertCreated();
        $this->postJson(self::URL, $this->payload(['unit_id' => $this->halfUnitA->id]))->assertStatus(409);
    }

    public function test_date_validation(): void
    {
        $this->preview(['last_date' => '2026-11-10'])->assertUnprocessable()
            ->assertJsonPath('errors.last_date.0', __('booking_series.validation.outside_booking_window', [], 'ar'));
        $this->preview(['first_date' => '2026-10-25'])->assertUnprocessable()
            ->assertJsonPath('errors.first_date.0', __('booking_series.validation.first_date_past', [], 'ar'));
        $this->preview(['last_date' => '2026-10-20'])->assertUnprocessable()
            ->assertJsonPath('errors.last_date.0', __('booking_series.validation.last_before_first', [], 'ar'));
        $this->preview(['first_date' => '2026-10-26', 'last_date' => '2026-10-26'])->assertOk()->assertJsonCount(1, 'data.dates');
    }

    public function test_field_validation_in_both_languages(): void
    {
        $this->postJson(self::URL, [])->assertUnprocessable()
            ->assertJsonPath('errors.unit_id.0', __('booking_series.validation.unit_id_invalid', [], 'ar'))
            ->assertJsonPath('errors.start_time.0', __('booking_series.validation.start_time_invalid', [], 'ar'))
            ->assertJsonPath('errors.payment_mode.0', __('booking_series.validation.payment_mode_invalid', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson(self::URL.'/preview', ['unit_id' => 'a', 'sport_id' => 'b', 'start_time' => '25:99', 'duration_minutes' => 'c', 'first_date' => 'x', 'last_date' => 'y'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.start_time.0', __('booking_series.validation.start_time_invalid', [], 'en'))
            ->assertJsonPath('errors.first_date.0', __('booking_series.validation.date_invalid', [], 'en'));

        $this->preview(['duration_minutes' => 90])->assertUnprocessable()->assertJsonValidationErrors('duration_minutes');
        $this->postJson(self::URL, $this->payload(['skip_unavailable' => 'maybe']))->assertUnprocessable()->assertJsonValidationErrors('skip_unavailable');
    }

    public function test_index_and_show_are_limited_to_the_owner(): void
    {
        $own = BookingSeries::factory()->for($this->user)->create();
        $newer = BookingSeries::factory()->for($this->user)->create();
        Booking::factory()->for($this->user)->create(['series_id' => $own->id, 'starts_at' => now()->addDays(9), 'ends_at' => now()->addDays(9)->addHour()]);
        Booking::factory()->for($this->user)->create(['series_id' => $own->id, 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour()]);
        $other = BookingSeries::factory()->create();

        $this->getJson(self::URL)->assertOk()
            ->assertJsonPath('data.*.id', [$newer->id, $own->id])
            ->assertJsonPath('data.1.bookings_count', 2);

        $response = $this->getJson(self::URL."/{$own->id}")->assertOk()->assertJsonCount(2, 'data.bookings');
        $this->assertLessThan($response->json('data.bookings.1.starts_at'), $response->json('data.bookings.0.starts_at'));

        $this->getJson(self::URL."/{$other->id}")->assertNotFound();
        $this->postJson(self::URL."/{$other->id}/cancel")->assertNotFound();
    }

    public function test_cancelling_a_series_keeps_the_bookings_past_the_deadline(): void
    {
        $series = BookingSeries::factory()->for($this->user)->create();
        $far = Booking::factory()->for($this->user)->create(['series_id' => $series->id, 'starts_at' => now()->addDays(8), 'ends_at' => now()->addDays(8)->addHour()]);
        $soonPending = Booking::factory()->pendingDeposit()->for($this->user)->create(['series_id' => $series->id, 'starts_at' => now()->addHours(3), 'ends_at' => now()->addHours(4)]);
        $soonConfirmed = Booking::factory()->for($this->user)->create(['series_id' => $series->id, 'starts_at' => now()->addHours(10), 'ends_at' => now()->addHours(11)]);
        $done = Booking::factory()->for($this->user)->create(['series_id' => $series->id, 'status' => 'completed', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHour()]);

        $this->postJson(self::URL."/{$series->id}/cancel", ['reason' => 'moving'])
            ->assertOk()
            ->assertJsonPath('message', __('booking_series.cancelled', [], 'ar'))
            ->assertJsonPath('data.cancelled_count', 2)
            ->assertJsonPath('data.kept', [$soonConfirmed->booking_number]);

        $this->assertSame('cancelled', $far->fresh()->status);
        $this->assertSame('cancelled', $soonPending->fresh()->status);
        $this->assertSame('confirmed', $soonConfirmed->fresh()->status);
        $this->assertSame('completed', $done->fresh()->status);
        $this->assertSame('cancelled', $series->fresh()->status);
        $this->assertNotNull($series->fresh()->cancelled_at);
        $this->assertTrue(AuditLog::query()->where('action', 'booking_series.cancelled')->where('entity_id', $series->id)->exists());

        $this->postJson(self::URL."/{$series->id}/cancel")->assertUnprocessable()
            ->assertJsonPath('message', __('booking_series.not_cancellable', [], 'ar'));
    }

    public function test_cancelling_a_single_booking_leaves_the_series_active(): void
    {
        $this->postJson(self::URL, $this->payload())->assertCreated();
        $series = BookingSeries::query()->sole();
        $booking = $series->bookings()->orderBy('starts_at')->first();

        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")->assertOk();

        $this->assertSame('active', $series->fresh()->status);
        $this->assertSame(2, $series->bookings()->where('status', 'pending_deposit')->count());
    }

    public function test_access_control(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->getJson(self::URL)->assertUnauthorized();
        $this->postJson(self::URL.'/preview', $this->payload())->assertUnauthorized();

        Sanctum::actingAs(Staff::factory()->create());
        $this->getJson(self::URL)->assertForbidden();
        $this->postJson(self::URL, $this->payload())->assertForbidden();
        $this->assertSame(0, BookingSeries::query()->count());
    }
}
