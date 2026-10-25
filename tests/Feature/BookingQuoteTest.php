<?php

namespace Tests\Feature;

use App\Models\BookableUnit;
use App\Models\Discount;
use App\Models\Facility;
use App\Models\PriceRule;
use App\Models\Setting;
use App\Models\Sport;
use App\Models\Staff;
use App\Models\User;
use App\Models\UserCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingQuoteTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/bookings/quote';

    private BookableUnit $unit;

    private Sport $sport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');

        $this->unit = BookableUnit::factory()->create();
        $this->sport = Sport::factory()->create();
        $this->unit->sports()->attach($this->sport);

        PriceRule::factory()->create([
            'unit_id' => $this->unit->id,
            'weekday' => null,
            'start_time' => '08:00',
            'end_time' => '23:00',
            'price_per_hour' => 100000,
            'valid_from' => '2026-01-01',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function params(array $overrides = []): array
    {
        return [
            'unit_id' => $this->unit->id,
            'sport_id' => $this->sport->id,
            'starts_at' => '2026-11-02 17:00',
            'duration_minutes' => 60,
            ...$overrides,
        ];
    }

    public function test_anonymous_quote_returns_prices_deposit_and_local_times(): void
    {
        $this->getJson(self::URL.'?'.http_build_query($this->params(['duration_minutes' => 120])))
            ->assertOk()
            ->assertExactJson(['data' => [
                'unit_id' => $this->unit->id,
                'sport_id' => $this->sport->id,
                'starts_at' => '2026-11-02T17:00:00+03:00',
                'ends_at' => '2026-11-02T19:00:00+03:00',
                'duration_minutes' => 120,
                'base_price' => 200000,
                'discount' => null,
                'discount_amount' => 0,
                'final_price' => 200000,
                'deposit_amount' => 60000,
                'currency' => 'SYP',
            ]]);
    }

    public function test_sport_is_optional_and_deposit_follows_the_setting_rounded_up(): void
    {
        Setting::factory()->create(['key' => 'booking.deposit_percent', 'value' => 33]);

        $this->getJson(self::URL.'?'.http_build_query($this->params(['sport_id' => null])))
            ->assertOk()
            ->assertJsonPath('data.sport_id', null)
            ->assertJsonPath('data.deposit_amount', 33000);

        PriceRule::query()->update(['price_per_hour' => 100001]);

        $this->getJson(self::URL.'?'.http_build_query($this->params()))
            ->assertOk()
            ->assertJsonPath('data.deposit_amount', (int) ceil(100001 * 33 / 100));
    }

    public function test_category_discount_applies_only_with_a_member_token(): void
    {
        $category = UserCategory::factory()->create();
        $discount = Discount::factory()->create([
            'type' => 'percent',
            'value' => 20,
            'target' => 'booking',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $discount->categories()->attach($category);

        $member = User::factory()->create();
        $member->categories()->attach($category);
        $outsider = User::factory()->create();
        $url = self::URL.'?'.http_build_query($this->params());

        $this->getJson($url)->assertOk()->assertJsonPath('data.discount', null)->assertJsonPath('data.final_price', 100000);

        Sanctum::actingAs($outsider);
        $this->getJson($url)->assertOk()->assertJsonPath('data.discount', null);

        Sanctum::actingAs($member);
        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.discount.id', $discount->id)
            ->assertJsonPath('data.discount.type', 'percent')
            ->assertJsonPath('data.discount.value', 20)
            ->assertJsonPath('data.discount_amount', 20000)
            ->assertJsonPath('data.final_price', 80000)
            ->assertJsonPath('data.deposit_amount', 24000);
    }

    public function test_a_staff_token_is_treated_as_a_guest(): void
    {
        $category = UserCategory::factory()->create();
        Discount::factory()->create(['starts_at' => now()->subDay(), 'ends_at' => now()->addDay()])->categories()->attach($category);

        Sanctum::actingAs(Staff::factory()->create());

        $this->getJson(self::URL.'?'.http_build_query($this->params()))
            ->assertOk()
            ->assertJsonPath('data.discount', null);
    }

    public function test_general_discount_applies_to_guests(): void
    {
        $discount = Discount::factory()->create(['type' => 'fixed', 'value' => 15000, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

        $this->getJson(self::URL.'?'.http_build_query($this->params()))
            ->assertOk()
            ->assertJsonPath('data.discount.id', $discount->id)
            ->assertJsonPath('data.final_price', 85000);
    }

    public function test_unpriced_time_is_a_422_on_starts_at(): void
    {
        $this->getJson(self::URL.'?'.http_build_query($this->params(['starts_at' => '2026-11-02 04:00'])))
            ->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', __('pricing.no_price', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->getJson(self::URL.'?'.http_build_query($this->params(['starts_at' => '2026-11-02 22:00', 'duration_minutes' => 120])))
            ->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', 'No price is defined for the requested time.');
    }

    public function test_required_fields_in_both_languages(): void
    {
        $this->getJson(self::URL)
            ->assertUnprocessable()
            ->assertJsonPath('errors.unit_id.0', __('pricing.validation.unit_id_invalid', [], 'ar'))
            ->assertJsonPath('errors.starts_at.0', __('pricing.validation.starts_at_invalid', [], 'ar'))
            ->assertJsonPath('errors.duration_minutes.0', __('pricing.validation.duration_invalid', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->getJson(self::URL.'?'.http_build_query(['unit_id' => 'x', 'sport_id' => 99999, 'starts_at' => 'tomorrow', 'duration_minutes' => 'long']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.unit_id.0', 'The unit must be the id of an existing bookable unit.')
            ->assertJsonPath('errors.sport_id.0', 'The sport must be the id of an existing sport.')
            ->assertJsonPath('errors.starts_at.0', 'The start is required in YYYY-MM-DD HH:MM format.')
            ->assertJsonPath('errors.duration_minutes.0', 'The duration must be a whole number of minutes between 1 and 600.');
    }

    public function test_unknown_inactive_and_inactive_facility_units_are_rejected(): void
    {
        $this->getJson(self::URL.'?'.http_build_query($this->params(['unit_id' => 99999])))
            ->assertUnprocessable()->assertJsonValidationErrors('unit_id');

        $this->unit->update(['is_active' => false]);
        $this->getJson(self::URL.'?'.http_build_query($this->params()))
            ->assertUnprocessable()
            ->assertJsonPath('errors.unit_id.0', __('pricing.validation.unit_unavailable', [], 'ar'));

        $this->unit->update(['is_active' => true]);
        Facility::query()->update(['is_active' => false]);
        $this->getJson(self::URL.'?'.http_build_query($this->params()))
            ->assertUnprocessable()->assertJsonValidationErrors('unit_id');
    }

    public function test_sport_must_be_offered_by_the_unit(): void
    {
        $other = Sport::factory()->create();

        $this->getJson(self::URL.'?'.http_build_query($this->params(['sport_id' => $other->id])))
            ->assertUnprocessable()
            ->assertJsonPath('errors.sport_id.0', __('pricing.validation.sport_not_offered', [], 'ar'));
    }

    public function test_duration_must_be_a_multiple_of_the_slot_length(): void
    {
        foreach ([30, 90, 61] as $minutes) {
            $this->getJson(self::URL.'?'.http_build_query($this->params(['duration_minutes' => $minutes])))
                ->assertUnprocessable()
                ->assertJsonPath('errors.duration_minutes.0', __('pricing.validation.duration_not_multiple', ['minutes' => 60], 'ar'));
        }

        $this->withHeader('Accept-Language', 'en')
            ->getJson(self::URL.'?'.http_build_query($this->params(['duration_minutes' => 90])))
            ->assertUnprocessable()
            ->assertJsonPath('errors.duration_minutes.0', 'The duration must be a multiple of 60 minutes and at least 60 minutes.');

        foreach ([0, 601, 660] as $minutes) {
            $this->getJson(self::URL.'?'.http_build_query($this->params(['duration_minutes' => $minutes])))
                ->assertUnprocessable()->assertJsonValidationErrors('duration_minutes');
        }

        Setting::factory()->create(['key' => 'booking.slot_minutes', 'value' => 30]);
        $this->getJson(self::URL.'?'.http_build_query($this->params(['duration_minutes' => 90])))->assertOk();
    }

    public function test_quote_route_is_not_shadowed_by_other_booking_routes(): void
    {
        $this->getJson(self::URL.'?'.http_build_query($this->params()))->assertOk()->assertJsonStructure(['data' => ['final_price']]);
    }
}
