<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Closure;
use App\Models\Discount;
use App\Models\Permission;
use App\Models\Role;
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

class AdminBookingsTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    private const URL = '/api/v1/admin/bookings';

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createBookingFixtures();
        $this->staff = $this->staffWithPermissions(['bookings.view', 'bookings.manage']);
        Sanctum::actingAs($this->staff);
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
            'guest_name' => 'Walk In',
            'guest_phone' => '0912345678',
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

    public function test_guest_booking_is_confirmed_cash_with_a_normalized_phone(): void
    {
        $this->book()->assertCreated()
            ->assertJsonPath('message', __('admin_bookings.created', [], 'ar'))
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.source', 'front_desk')
            ->assertJsonPath('data.payment_mode', 'cash_full')
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.guest_name', 'Walk In')
            ->assertJsonPath('data.guest_phone', '+963912345678')
            ->assertJsonPath('data.final_price', 100000)
            ->assertJsonPath('data.deposit_amount', 0)
            ->assertJsonPath('data.deposit_due_at', null)
            ->assertJsonPath('data.balance_due', 100000)
            ->assertJsonPath('data.created_by_staff.id', $this->staff->id);

        $booking = Booking::query()->sole();
        $this->assertSame($this->staff->id, $booking->created_by_staff_id);
        $this->assertSame(2, SpaceReservation::query()->where('booking_id', $booking->id)->count());
        $this->assertTrue(AuditLog::query()->where('action', 'booking.created')->where('entity_id', $booking->id)->where('actor_staff_id', $this->staff->id)->exists());
    }

    public function test_registered_user_booking_gets_the_users_discount(): void
    {
        $user = User::factory()->create();
        $category = UserCategory::factory()->create();
        $user->categories()->attach($category);
        Discount::factory()->create(['type' => 'percent', 'value' => 20, 'target' => 'booking'])->categories()->attach($category);

        $this->book(['guest_name' => null, 'guest_phone' => null, 'user_id' => $user->id])->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.user.phone', $user->phone)
            ->assertJsonPath('data.discount_amount', 20000)
            ->assertJsonPath('data.final_price', 80000);

        $this->book(['starts_at' => '2026-10-27 20:00'])->assertCreated()
            ->assertJsonPath('data.discount_amount', 0)
            ->assertJsonPath('data.final_price', 100000);
    }

    public function test_payment_modes_for_front_desk(): void
    {
        $this->book(['payment_mode' => 'deposit_then_cash'])->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.deposit_amount', 30000)
            ->assertJsonPath('data.deposit_due_at', null);

        $this->book(['payment_mode' => 'online_full', 'starts_at' => '2026-10-27 20:00'])->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.deposit_amount', 0);

        $this->book(['payment_mode' => 'free', 'starts_at' => '2026-10-27 12:00'])->assertUnprocessable()->assertJsonValidationErrors('payment_mode');
    }

    public function test_staff_are_exempt_from_the_window_but_not_from_past_hours_closures_or_conflicts(): void
    {
        $this->book(['starts_at' => '2026-12-01 12:00'])->assertCreated();

        $this->book(['starts_at' => '2026-10-26 10:00'])->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', __('bookings.validation.past_start', [], 'ar'));
        $this->book(['starts_at' => '2026-10-28 06:00'])->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', __('bookings.validation.outside_operating_hours', [], 'ar'));

        $start = app(DisplayTimezone::class)->localToUtc('2026-10-29 12:00');
        Closure::factory()->create(['facility_id' => $this->facility->id, 'starts_at' => $start, 'ends_at' => $start->addHour()]);
        $this->book(['starts_at' => '2026-10-29 12:00'])->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', __('bookings.validation.closed', [], 'ar'));

        $this->book(['starts_at' => '2026-12-01 12:00', 'unit_id' => $this->halfUnitA->id])->assertStatus(409);
        $this->book(['starts_at' => '2026-12-01 12:00', 'unit_id' => $this->halfUnitB->id])->assertStatus(409);
        $this->book(['starts_at' => '2026-12-01 13:00', 'unit_id' => $this->halfUnitB->id])->assertCreated();
    }

    public function test_store_validation_in_both_languages(): void
    {
        $this->postJson(self::URL, [])->assertUnprocessable()
            ->assertJsonPath('errors.unit_id.0', __('admin_bookings.validation.unit_id_invalid', [], 'ar'))
            ->assertJsonPath('errors.guest_name.0', __('admin_bookings.validation.guest_name_required', [], 'ar'))
            ->assertJsonPath('errors.guest_phone.0', __('admin_bookings.validation.guest_phone_required', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson(self::URL, $this->payload(['guest_phone' => '12345', 'guest_name' => 'x', 'user_id' => 'abc', 'duration_minutes' => 'z']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.guest_phone.0', __('admin_bookings.validation.guest_phone_invalid', [], 'en'))
            ->assertJsonPath('errors.guest_name.0', __('admin_bookings.validation.guest_name_invalid', [], 'en'))
            ->assertJsonPath('errors.user_id.0', __('admin_bookings.validation.user_id_invalid', [], 'en'));

        $this->book(['user_id' => 999999, 'guest_name' => null, 'guest_phone' => null])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->book(['duration_minutes' => 90])->assertUnprocessable()->assertJsonValidationErrors('duration_minutes');
    }

    public function test_index_filters_and_orders_by_start(): void
    {
        $user = User::factory()->create(['phone' => '+963911111111']);
        $first = $this->makeBooking(['user_id' => $user->id, 'starts_at' => now()->addDays(2)->setTime(9, 0), 'booking_number' => 'BKAAA111']);
        $second = $this->makeBooking(['starts_at' => now()->addDays(1)->setTime(9, 0), 'booking_number' => 'BKBBB222', 'guest_name' => 'G', 'guest_phone' => '+963922222222', 'user_id' => null, 'status' => 'cancelled']);
        $other = Booking::factory()->create(['starts_at' => now()->addDays(20)]);

        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.*.id', [$second->id, $first->id, $other->id]);
        $this->getJson(self::URL.'?status=cancelled')->assertOk()->assertJsonPath('data.*.id', [$second->id]);
        $this->getJson(self::URL.'?user_id='.$user->id)->assertOk()->assertJsonPath('data.*.id', [$first->id]);
        $this->getJson(self::URL.'?phone=911111')->assertOk()->assertJsonPath('data.*.id', [$first->id]);
        $this->getJson(self::URL.'?phone=922222')->assertOk()->assertJsonPath('data.*.id', [$second->id]);
        $this->getJson(self::URL.'?booking_number=BBB')->assertOk()->assertJsonPath('data.*.id', [$second->id]);
        $this->getJson(self::URL.'?unit_id='.$first->unit_id)->assertOk()->assertJsonPath('data.*.id', [$first->id]);
        $this->getJson(self::URL.'?facility_id='.$first->unit->facility_id)->assertOk()->assertJsonPath('data.*.id', [$first->id]);
        $this->getJson(self::URL.'?date='.now()->addDays(2)->toDateString())->assertOk()->assertJsonPath('data.*.id', [$first->id]);
        $this->getJson(self::URL.'?from='.now()->addDays(2)->toDateString().'&to='.now()->addDays(2)->toDateString())->assertOk()->assertJsonPath('data.*.id', [$first->id]);
        $this->getJson(self::URL.'?status=bad&date=x&per_page=0')->assertUnprocessable()->assertJsonValidationErrors(['status', 'date', 'per_page']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeBooking(array $attributes = []): Booking
    {
        $startsAt = $attributes['starts_at'] ?? now()->addDay();

        return Booking::factory()->create([...$attributes, 'starts_at' => $startsAt, 'ends_at' => $startsAt->copy()->addHour()]);
    }

    public function test_show_loads_the_relations(): void
    {
        $booking = $this->makeBooking();

        $this->getJson(self::URL."/{$booking->id}")->assertOk()
            ->assertJsonPath('data.id', $booking->id)
            ->assertJsonPath('data.user.id', $booking->user_id)
            ->assertJsonPath('data.unit.id', $booking->unit_id);
        $this->getJson(self::URL.'/999999')->assertNotFound();
    }

    public function test_cancel_requires_a_reason_and_releases_the_slot_any_time_before_the_end(): void
    {
        $id = $this->book()->assertCreated()->json('data.id');

        $this->postJson(self::URL."/{$id}/cancel", [])->assertUnprocessable()
            ->assertJsonPath('errors.reason.0', __('admin_bookings.validation.reason_required', [], 'ar'));

        $this->postJson(self::URL."/{$id}/cancel", ['reason' => 'customer called'])->assertOk()
            ->assertJsonPath('message', __('admin_bookings.cancelled', [], 'ar'))
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_reason', 'customer called');

        $booking = Booking::query()->findOrFail($id);
        $this->assertSame($this->staff->id, $booking->cancelled_by_staff_id);
        $this->assertNull($booking->cancelled_by_user_id);
        $this->assertSame(0, SpaceReservation::query()->where('booking_id', $id)->where('is_active', true)->count());
        $this->assertTrue(AuditLog::query()->where('action', 'booking.cancelled')->where('entity_id', $id)->exists());

        $this->postJson(self::URL."/{$id}/cancel", ['reason' => 'again'])->assertUnprocessable();
        $this->book()->assertCreated();
    }

    public function test_staff_can_cancel_inside_the_user_deadline_but_not_after_the_end(): void
    {
        $soon = $this->makeBooking(['starts_at' => now()->addHours(2)]);
        $running = $this->makeBooking(['starts_at' => now()->subMinutes(30)]);
        $ended = $this->makeBooking(['starts_at' => now()->subHours(3)]);

        $this->postJson(self::URL."/{$soon->id}/cancel", ['reason' => 'x'])->assertOk();
        $this->postJson(self::URL."/{$running->id}/cancel", ['reason' => 'x'])->assertOk();
        $this->postJson(self::URL."/{$ended->id}/cancel", ['reason' => 'x'])->assertUnprocessable()
            ->assertJsonPath('message', __('bookings.not_cancellable', [], 'ar'));
    }

    public function test_no_show_only_for_started_confirmed_bookings_and_keeps_reservations(): void
    {
        $started = $this->makeBooking(['starts_at' => now()->subMinutes(30)]);
        SpaceReservation::factory()->create(['booking_id' => $started->id, 'starts_at' => $started->starts_at, 'ends_at' => $started->ends_at]);
        $future = $this->makeBooking(['starts_at' => now()->addHours(2)]);
        $pending = Booking::factory()->pendingDeposit()->create(['starts_at' => now()->subMinutes(30), 'ends_at' => now()->addMinutes(30)]);

        $this->postJson(self::URL."/{$started->id}/no-show")->assertOk()
            ->assertJsonPath('message', __('admin_bookings.no_show', [], 'ar'))
            ->assertJsonPath('data.status', 'no_show');
        $this->assertSame(1, SpaceReservation::query()->where('booking_id', $started->id)->where('is_active', true)->count());
        $this->assertTrue(AuditLog::query()->where('action', 'booking.no_show')->where('entity_id', $started->id)->exists());

        foreach ([$future, $pending, $started] as $booking) {
            $this->postJson(self::URL."/{$booking->id}/no-show")->assertUnprocessable()
                ->assertJsonPath('message', __('admin_bookings.cannot_mark_no_show', [], 'ar'));
        }
    }

    public function test_access_control(): void
    {
        $booking = $this->makeBooking();

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->getJson(self::URL)->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson(self::URL)->assertForbidden();
        $this->postJson(self::URL, $this->payload())->assertForbidden();

        Sanctum::actingAs($this->staffWithPermissions(['sports.manage']));
        $this->getJson(self::URL)->assertForbidden();
        $this->getJson(self::URL."/{$booking->id}")->assertForbidden();
        $this->postJson(self::URL, $this->payload())->assertForbidden();

        Sanctum::actingAs($this->staffWithPermissions(['bookings.view']));
        $this->getJson(self::URL)->assertOk();
        $this->postJson(self::URL, $this->payload())->assertForbidden();
        $this->postJson(self::URL."/{$booking->id}/cancel", ['reason' => 'x'])->assertForbidden();
        $this->postJson(self::URL."/{$booking->id}/no-show")->assertForbidden();

        Sanctum::actingAs($this->staffWithPermissions(['bookings.manage']));
        $this->getJson(self::URL)->assertForbidden();
        $this->postJson(self::URL, $this->payload())->assertCreated();

        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_staff_token_is_rejected_on_the_customer_booking_routes(): void
    {
        $this->getJson('/api/v1/bookings')->assertForbidden();
    }
}
