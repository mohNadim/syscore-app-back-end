<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Booking;
use App\Models\Discount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SpaceReservation;
use App\Models\Staff;
use App\Models\User;
use App\Models\UserCategory;
use App\Services\DisplayTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBookingFixtures;
use Tests\TestCase;

class BookingExtrasTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createBookingFixtures();
        $this->user = User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function bookingPayload(array $overrides = []): array
    {
        return [
            'unit_id' => $this->fullUnit->id,
            'sport_id' => $this->sport->id,
            'starts_at' => '2026-10-27 18:00',
            'duration_minutes' => 60,
            'payment_mode' => 'cash_only',
            ...$overrides,
        ];
    }

    private function disableCashOnly(): void
    {
        Setting::query()->create(['key' => 'booking.cash_only_enabled', 'value' => false]);
    }

    public function test_cash_only_booking_is_confirmed_without_deposit_or_expiry(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/bookings', $this->bookingPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.payment_mode', 'cash_only')
            ->assertJsonPath('data.deposit_amount', 0)
            ->assertJsonPath('data.deposit_due_at', null)
            ->assertJsonPath('data.paid_amount', 0)
            ->assertJsonPath('data.balance_due', 100000);

        $booking = Booking::query()->sole();
        $this->assertSame(2, SpaceReservation::query()->where('booking_id', $booking->id)->where('is_active', true)->count());

        $this->artisan('bookings:expire-unpaid')->assertSuccessful();
        $this->assertSame('confirmed', $booking->refresh()->status);
    }

    public function test_cash_only_booking_completes_after_it_ends(): void
    {
        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/bookings', $this->bookingPayload())->assertCreated();

        $this->travelTo(CarbonImmutable::parse('2026-10-28 00:00:00', 'UTC'));
        $this->artisan('bookings:complete-past')->assertSuccessful();

        $this->assertSame('completed', Booking::query()->sole()->status);
    }

    public function test_disabled_cash_only_is_rejected_with_a_code(): void
    {
        $this->disableCashOnly();
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/bookings', $this->bookingPayload())
            ->assertStatus(422)
            ->assertJsonPath('code', 'payment_option_disabled')
            ->assertJsonPath('message', __('bookings.payment_option_disabled', [], 'ar'));

        $this->assertSame(0, Booking::query()->count());
        $this->postJson('/api/v1/bookings', $this->bookingPayload(['payment_mode' => 'online_full']))->assertCreated();
    }

    public function test_unknown_payment_mode_is_still_rejected(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/bookings', $this->bookingPayload(['payment_mode' => 'cash_full']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_mode']);
    }

    public function test_series_supports_cash_only_and_respects_the_setting(): void
    {
        Sanctum::actingAs($this->user);
        $payload = [
            'unit_id' => $this->fullUnit->id,
            'sport_id' => $this->sport->id,
            'start_time' => '18:00',
            'duration_minutes' => 60,
            'first_date' => '2026-10-27',
            'last_date' => '2026-10-27',
            'payment_mode' => 'cash_only',
        ];

        $this->postJson('/api/v1/booking-series', $payload)->assertCreated();
        $this->assertSame('confirmed', Booking::query()->sole()->status);

        $this->disableCashOnly();
        $this->postJson('/api/v1/booking-series', [...$payload, 'first_date' => '2026-10-28', 'last_date' => '2026-10-28'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'payment_option_disabled');
    }

    public function test_app_config_lists_enabled_payment_options(): void
    {
        $this->getJson('/api/v1/app-config')
            ->assertOk()
            ->assertJsonPath('data.booking.payment_options', ['online_full', 'deposit_then_cash', 'cash_only']);

        $this->disableCashOnly();

        $this->getJson('/api/v1/app-config')
            ->assertJsonPath('data.booking.payment_options', ['online_full', 'deposit_then_cash']);
    }

    public function test_admin_can_toggle_the_cash_only_setting(): void
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->firstOrCreate(['code' => 'settings.manage'], ['description' => 'settings.manage']));
        Sanctum::actingAs(Staff::factory()->create(['role_id' => $role->id]));

        $this->getJson('/api/v1/admin/settings')->assertJsonPath('data.booking.cash_only_enabled', true);

        $this->putJson('/api/v1/admin/settings', ['booking' => ['cash_only_enabled' => false]])
            ->assertOk()
            ->assertJsonPath('data.booking.cash_only_enabled', false);

        $this->putJson('/api/v1/admin/settings', ['booking' => ['cash_only_enabled' => 'maybe']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['booking.cash_only_enabled']);
    }

    public function test_nearest_availability_lists_the_earliest_future_slot_per_unit(): void
    {
        $this->reserveLocal([$this->spaceA->id, $this->spaceB->id], '2026-10-26 12:00');

        $response = $this->getJson('/api/v1/availability/nearest?facility_id='.$this->facility->id.'&sport_id='.$this->sport->id)
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertSame('2026-10-26T13:00:00+03:00', $data[0]['starts_at']);
        $this->assertSame('2026-10-26T14:00:00+03:00', $data[0]['ends_at']);
        $this->assertSame(100000, $data[0]['base_price']);
        $this->assertSame($this->facility->id, $data[0]['facility']['id']);
        $this->assertArrayHasKey('name_ar', $data[0]['unit']);
        $this->assertArrayHasKey('name_en', $data[0]['unit']);

        $this->getJson('/api/v1/availability/nearest?limit=2')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_nearest_availability_is_empty_after_closing_and_validates_input(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-26 20:00:00', 'UTC'));

        $this->getJson('/api/v1/availability/nearest')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/availability/nearest?limit=51')->assertStatus(422)->assertJsonValidationErrors(['limit']);
        $this->getJson('/api/v1/availability/nearest?facility_id=9999')->assertStatus(422)->assertJsonValidationErrors(['facility_id']);
    }

    public function test_announcement_detail_is_visible_only_to_its_audience(): void
    {
        $category = UserCategory::factory()->create();
        $member = User::factory()->create();
        $member->categories()->attach($category);

        $everyone = Announcement::factory()->create(['audience' => 'all']);
        $forCategory = Announcement::factory()->create(['audience' => 'category', 'user_category_id' => $category->id]);
        $forUsers = Announcement::factory()->create(['audience' => 'users']);
        $forUsers->recipients()->attach($this->user);

        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/announcements/'.$everyone->id)
            ->assertOk()
            ->assertJsonPath('data.id', $everyone->id)
            ->assertJsonPath('data.title_ar', $everyone->title_ar)
            ->assertJsonPath('data.body_en', $everyone->body_en);
        $this->getJson('/api/v1/announcements/'.$forUsers->id)->assertOk();
        $this->getJson('/api/v1/announcements/'.$forCategory->id)->assertNotFound();

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/announcements/'.$forCategory->id)->assertOk();
        $this->getJson('/api/v1/announcements/'.$forUsers->id)->assertNotFound();
        $this->getJson('/api/v1/announcements/999999')->assertNotFound();
    }

    public function test_announcement_and_discount_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/announcements/1')->assertUnauthorized();
        $this->getJson('/api/v1/discounts')->assertUnauthorized();
        $this->getJson('/api/v1/discounts/1')->assertUnauthorized();
    }

    public function test_discounts_list_and_detail_show_only_active_discounts_for_the_user(): void
    {
        $category = UserCategory::factory()->create();
        $other = UserCategory::factory()->create();
        $this->user->categories()->attach($category);

        $open = Discount::factory()->create();
        $mine = Discount::factory()->create();
        $mine->categories()->attach($category);
        $notMine = Discount::factory()->create();
        $notMine->categories()->attach($other);
        $inactive = Discount::factory()->inactive()->create();
        $expired = Discount::factory()->create(['starts_at' => now()->subMonth(), 'ends_at' => now()->subDay()]);

        Sanctum::actingAs($this->user);

        $ids = collect($this->getJson('/api/v1/discounts')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$open->id, $mine->id], $ids);

        $this->getJson('/api/v1/discounts/'.$mine->id)
            ->assertOk()
            ->assertJsonPath('data.id', $mine->id)
            ->assertJsonPath('data.name_ar', $mine->name_ar)
            ->assertJsonPath('data.name_en', $mine->name_en)
            ->assertJsonMissingPath('data.created_by_staff_id');

        foreach ([$notMine, $inactive, $expired] as $hidden) {
            $this->getJson('/api/v1/discounts/'.$hidden->id)->assertNotFound();
        }
    }

    /**
     * @param  list<int>  $spaceIds
     */
    private function reserveLocal(array $spaceIds, string $localStart): void
    {
        $startsAt = app(DisplayTimezone::class)->localToUtc($localStart);
        $booking = Booking::factory()->create(['starts_at' => $startsAt, 'ends_at' => $startsAt->addHour()]);

        foreach ($spaceIds as $spaceId) {
            SpaceReservation::factory()->create([
                'space_id' => $spaceId,
                'booking_id' => $booking->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addHour(),
                'is_active' => true,
            ]);
        }
    }
}
