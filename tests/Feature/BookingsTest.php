<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Closure;
use App\Models\Discount;
use App\Models\OperatingHour;
use App\Models\PriceRule;
use App\Models\Setting;
use App\Models\SpaceReservation;
use App\Models\Sport;
use App\Models\Staff;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\UserCategory;
use App\Services\DisplayTimezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBookingFixtures;
use Tests\TestCase;

class BookingsTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    private const URL = '/api/v1/bookings';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createBookingFixtures();
        $this->user = Sanctum::actingAs(User::factory()->create());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'unit_id' => $this->fullUnit->id,
            'sport_id' => $this->sport->id,
            'starts_at' => '2026-10-27 18:00',
            'duration_minutes' => 60,
            'payment_mode' => 'deposit_then_cash',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function book(array $overrides = []): TestResponse
    {
        return $this->postJson(self::URL, $this->payload($overrides));
    }

    public function test_deposit_booking_is_created_pending_with_price_and_reservations(): void
    {
        $this->book(['duration_minutes' => 120])
            ->assertCreated()
            ->assertJsonPath('message', __('bookings.created', [], 'ar'))
            ->assertJsonPath('data.status', 'pending_deposit')
            ->assertJsonPath('data.source', 'app')
            ->assertJsonPath('data.payment_mode', 'deposit_then_cash')
            ->assertJsonPath('data.base_price', 200000)
            ->assertJsonPath('data.final_price', 200000)
            ->assertJsonPath('data.deposit_amount', 60000)
            ->assertJsonPath('data.paid_amount', 0)
            ->assertJsonPath('data.balance_due', 200000)
            ->assertJsonPath('data.duration_minutes', 120)
            ->assertJsonPath('data.starts_at', '2026-10-27T18:00:00+03:00')
            ->assertJsonPath('data.deposit_due_at', '2026-10-26T12:00:00+03:00')
            ->assertJsonPath('data.user_id', $this->user->id)
            ->assertJsonPath('data.unit.id', $this->fullUnit->id);

        $booking = Booking::query()->sole();
        $this->assertMatchesRegularExpression('/^BK261026[A-Z0-9]{6}$/', $booking->booking_number);
        $this->assertSame(2, SpaceReservation::query()->where('booking_id', $booking->id)->where('is_active', true)->count());
        $this->assertTrue(AuditLog::query()->where('action', 'booking.created')->where('entity_id', $booking->id)->exists());
    }

    public function test_online_full_requires_the_whole_price_as_deposit(): void
    {
        $this->book(['payment_mode' => 'online_full'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_deposit')
            ->assertJsonPath('data.deposit_amount', 100000);
    }

    public function test_member_discount_is_applied_to_the_booking(): void
    {
        $category = UserCategory::factory()->create();
        $this->user->categories()->attach($category);
        $discount = Discount::factory()->create(['type' => 'percent', 'value' => 50, 'target' => 'booking']);
        $discount->categories()->attach($category);

        $this->book()->assertCreated()
            ->assertJsonPath('data.base_price', 100000)
            ->assertJsonPath('data.discount_amount', 50000)
            ->assertJsonPath('data.final_price', 50000)
            ->assertJsonPath('data.deposit_amount', 15000)
            ->assertJsonPath('data.discount.id', $discount->id);

        $this->assertSame($discount->id, Booking::query()->sole()->discount_id);
    }

    public function test_free_booking_is_confirmed_at_once_without_deposit(): void
    {
        $discount = Discount::factory()->create(['type' => 'percent', 'value' => 100, 'target' => 'booking']);

        $this->book()->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.final_price', 0)
            ->assertJsonPath('data.deposit_amount', 0)
            ->assertJsonPath('data.deposit_due_at', null)
            ->assertJsonPath('data.discount.id', $discount->id);
    }

    public function test_full_booking_blocks_both_halves_and_the_same_full_slot(): void
    {
        $this->book()->assertCreated();

        foreach ([$this->fullUnit, $this->halfUnitA, $this->halfUnitB] as $unit) {
            $this->book(['unit_id' => $unit->id])
                ->assertStatus(409)
                ->assertJsonPath('message', __('bookings.slot_taken', [], 'ar'));
        }

        $this->assertSame(1, Booking::query()->count());
    }

    public function test_half_booking_blocks_the_full_unit_and_the_same_half_but_not_the_other_half(): void
    {
        $this->book(['unit_id' => $this->halfUnitA->id])->assertCreated();

        $this->book(['unit_id' => $this->fullUnit->id])->assertStatus(409);
        $this->book(['unit_id' => $this->halfUnitA->id])->assertStatus(409);
        $this->book(['unit_id' => $this->halfUnitB->id])->assertCreated();

        $this->assertSame(2, Booking::query()->count());
    }

    public function test_partial_overlap_conflicts_but_touching_edges_do_not(): void
    {
        $this->book(['starts_at' => '2026-10-27 18:00'])->assertCreated();

        $this->book(['starts_at' => '2026-10-27 17:00', 'duration_minutes' => 120])->assertStatus(409);
        $this->book(['starts_at' => '2026-10-27 18:00', 'duration_minutes' => 120])->assertStatus(409);
        $this->book(['starts_at' => '2026-10-27 17:00'])->assertCreated();
        $this->book(['starts_at' => '2026-10-27 19:00'])->assertCreated();
    }

    public function test_cancelled_bookings_free_the_slot(): void
    {
        $id = $this->book()->assertCreated()->json('data.id');
        $this->postJson(self::URL."/{$id}/cancel")->assertOk();

        $this->book()->assertCreated();
    }

    public function test_training_session_reservation_blocks_a_booking(): void
    {
        $startsAt = app(DisplayTimezone::class)->localToUtc('2026-10-27 18:00');
        $session = TrainingSession::factory()->create(['unit_id' => $this->halfUnitB->id, 'starts_at' => $startsAt, 'ends_at' => $startsAt->addMinutes(90)]);
        SpaceReservation::factory()->forSession()->create([
            'space_id' => $this->spaceB->id,
            'session_id' => $session->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(90),
        ]);

        $this->book(['starts_at' => '2026-10-27 19:00'])->assertStatus(409);
        $this->book(['unit_id' => $this->halfUnitA->id])->assertCreated();
    }

    public function test_duration_rules(): void
    {
        $this->book(['duration_minutes' => 90])->assertUnprocessable()
            ->assertJsonPath('errors.duration_minutes.0', __('bookings.validation.duration_not_multiple', ['minutes' => 60], 'ar'));
        $this->book(['duration_minutes' => 660])->assertUnprocessable()->assertJsonValidationErrors('duration_minutes');

        Setting::factory()->create(['key' => 'booking.slot_minutes', 'value' => 30]);
        $this->book(['duration_minutes' => 30, 'starts_at' => '2026-10-27 18:30'])->assertCreated();
        $this->book(['duration_minutes' => 45])->assertUnprocessable();
    }

    public function test_past_start_is_rejected(): void
    {
        $this->book(['starts_at' => '2026-10-26 11:00'])->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', __('bookings.validation.past_start', [], 'ar'));
        $this->book(['starts_at' => '2026-10-26 12:00'])->assertCreated();
    }

    public function test_start_beyond_the_advance_window_is_rejected(): void
    {
        $this->book(['starts_at' => '2026-11-10 12:00'])->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', __('bookings.validation.outside_booking_window', [], 'ar'));
        $this->book(['starts_at' => '2026-11-09 21:00'])->assertCreated();
    }

    public function test_outside_operating_hours_is_rejected(): void
    {
        $this->book(['starts_at' => '2026-10-27 07:00'])->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', __('bookings.validation.outside_operating_hours', [], 'ar'));
        $this->book(['starts_at' => '2026-10-27 21:30'])->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', __('bookings.validation.outside_operating_hours', [], 'ar'));
        $this->book(['starts_at' => '2026-10-27 21:00'])->assertCreated();

        OperatingHour::query()->where('weekday', 3)->delete();
        $this->book(['starts_at' => '2026-10-28 12:00'])->assertUnprocessable();
    }

    public function test_closed_facility_or_space_is_rejected(): void
    {
        $start = app(DisplayTimezone::class)->localToUtc('2026-10-27 12:00');
        Closure::factory()->create(['facility_id' => null, 'space_id' => $this->spaceA->id, 'starts_at' => $start, 'ends_at' => $start->addHour()]);

        $this->book(['starts_at' => '2026-10-27 12:00'])->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', __('bookings.validation.closed', [], 'ar'));
        $this->book(['starts_at' => '2026-10-27 12:00', 'unit_id' => $this->halfUnitB->id])->assertCreated();
    }

    public function test_unpriced_interval_is_rejected(): void
    {
        PriceRule::query()->where('unit_id', $this->fullUnit->id)->update(['start_time' => '08:00', 'end_time' => '12:00']);

        $this->book(['starts_at' => '2026-10-27 11:00', 'duration_minutes' => 120])->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', __('pricing.no_price', [], 'ar'));
        $this->assertSame(0, Booking::query()->count());
    }

    public function test_unit_and_sport_must_be_bookable(): void
    {
        $this->fullUnit->update(['is_active' => false]);
        $this->book()->assertUnprocessable()->assertJsonValidationErrors('unit_id');

        $this->fullUnit->update(['is_active' => true]);
        $other = Sport::factory()->create();
        $this->book(['sport_id' => $other->id])->assertUnprocessable()
            ->assertJsonPath('errors.sport_id.0', __('bookings.validation.sport_not_offered', [], 'ar'));
    }

    public function test_validation_messages_in_both_languages(): void
    {
        $this->postJson(self::URL, [])->assertUnprocessable()
            ->assertJsonPath('errors.unit_id.0', __('bookings.validation.unit_id_invalid', [], 'ar'))
            ->assertJsonPath('errors.sport_id.0', __('bookings.validation.sport_id_invalid', [], 'ar'))
            ->assertJsonPath('errors.starts_at.0', __('bookings.validation.starts_at_invalid', [], 'ar'))
            ->assertJsonPath('errors.duration_minutes.0', __('bookings.validation.duration_invalid', [], 'ar'))
            ->assertJsonPath('errors.payment_mode.0', __('bookings.validation.payment_mode_invalid', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson(self::URL, ['unit_id' => 'x', 'sport_id' => 'y', 'starts_at' => 'z', 'duration_minutes' => 'w', 'payment_mode' => 'cash_full'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.unit_id.0', __('bookings.validation.unit_id_invalid', [], 'en'))
            ->assertJsonPath('errors.payment_mode.0', __('bookings.validation.payment_mode_invalid', [], 'en'));
    }

    public function test_user_lists_only_own_bookings_with_scope_and_status_filters(): void
    {
        $upcoming = Booking::factory()->for($this->user)->create(['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour()]);
        $later = Booking::factory()->for($this->user)->create(['starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHour()]);
        $past = Booking::factory()->for($this->user)->create(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHour(), 'status' => 'completed']);
        $older = Booking::factory()->for($this->user)->create(['starts_at' => now()->subDays(9), 'ends_at' => now()->subDays(9)->addHour(), 'status' => 'cancelled']);
        Booking::factory()->create();

        $this->getJson(self::URL.'?scope=upcoming')->assertOk()
            ->assertJsonPath('data.*.id', [$upcoming->id, $later->id]);
        $this->getJson(self::URL.'?scope=past')->assertOk()
            ->assertJsonPath('data.*.id', [$past->id, $older->id]);
        $this->getJson(self::URL.'?status=cancelled')->assertOk()
            ->assertJsonPath('data.*.id', [$older->id]);
        $this->getJson(self::URL)->assertOk()->assertJsonCount(4, 'data');
        $this->getJson(self::URL.'?scope=x&status=y&per_page=0')->assertUnprocessable()
            ->assertJsonValidationErrors(['scope', 'status', 'per_page']);
    }

    public function test_show_returns_own_booking_and_404_for_others(): void
    {
        $own = Booking::factory()->for($this->user)->create();
        $other = Booking::factory()->create();

        $this->getJson(self::URL."/{$own->id}")->assertOk()->assertJsonPath('data.id', $own->id);
        $this->getJson(self::URL."/{$other->id}")->assertNotFound();
        $this->postJson(self::URL."/{$other->id}/cancel")->assertNotFound();
        $this->assertSame('confirmed', $other->fresh()->status);
    }

    public function test_pending_booking_can_be_cancelled_at_any_time_before_start(): void
    {
        $id = $this->book(['starts_at' => '2026-10-26 12:00'])->assertCreated()->json('data.id');

        $this->postJson(self::URL."/{$id}/cancel", ['reason' => 'changed my mind'])
            ->assertOk()
            ->assertJsonPath('message', __('bookings.cancelled', [], 'ar'))
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_reason', 'changed my mind');

        $booking = Booking::query()->findOrFail($id);
        $this->assertSame($this->user->id, $booking->cancelled_by_user_id);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertSame(0, SpaceReservation::query()->where('booking_id', $id)->where('is_active', true)->count());
        $this->assertTrue(AuditLog::query()->where('action', 'booking.cancelled')->where('entity_id', $id)->exists());
    }

    public function test_confirmed_booking_can_be_cancelled_only_before_the_deadline(): void
    {
        $early = Booking::factory()->for($this->user)->create(['starts_at' => now()->addHours(25), 'ends_at' => now()->addHours(26)]);
        $late = Booking::factory()->for($this->user)->create(['starts_at' => now()->addHours(23), 'ends_at' => now()->addHours(24)]);

        $this->postJson(self::URL."/{$late->id}/cancel")->assertUnprocessable()
            ->assertJsonPath('message', __('bookings.cancellation_deadline_passed', [], 'ar'));
        $this->assertSame('confirmed', $late->fresh()->status);

        $this->postJson(self::URL."/{$early->id}/cancel")->assertOk();
    }

    public function test_started_or_already_cancelled_bookings_are_not_cancellable(): void
    {
        $started = Booking::factory()->pendingDeposit()->for($this->user)->create(['starts_at' => now()->subMinutes(10), 'ends_at' => now()->addMinutes(50)]);
        $cancelled = Booking::factory()->cancelled()->for($this->user)->create();

        $this->postJson(self::URL."/{$started->id}/cancel")->assertUnprocessable()
            ->assertJsonPath('message', __('bookings.not_cancellable', [], 'ar'));
        $this->postJson(self::URL."/{$cancelled->id}/cancel")->assertUnprocessable();
        $this->postJson(self::URL."/{$started->id}/cancel", ['reason' => str_repeat('a', 256)])->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_access_control(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->getJson(self::URL)->assertUnauthorized();
        $this->postJson(self::URL, $this->payload())->assertUnauthorized();

        Sanctum::actingAs(Staff::factory()->create());
        $this->getJson(self::URL)->assertForbidden();
        $this->postJson(self::URL, $this->payload())->assertForbidden();
        $this->assertSame(0, Booking::query()->count());
    }
}
