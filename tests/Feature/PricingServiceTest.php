<?php

namespace Tests\Feature;

use App\Exceptions\PriceUnavailableException;
use App\Models\Academy;
use App\Models\AcademyGroup;
use App\Models\BookableUnit;
use App\Models\Discount;
use App\Models\PriceRule;
use App\Services\DisplayTimezone;
use App\Services\Pricing\PriceQuote;
use App\Services\Pricing\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricing = app(PricingService::class);
    }

    private function local(string $value): CarbonImmutable
    {
        return app(DisplayTimezone::class)->localToUtc($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rule(BookableUnit $unit, string $from, string $to, int $pricePerHour, array $attributes = []): PriceRule
    {
        return PriceRule::factory()->create([
            'unit_id' => $unit->id,
            'weekday' => null,
            'start_time' => $from,
            'end_time' => $to,
            'price_per_hour' => $pricePerHour,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
            ...$attributes,
        ]);
    }

    public function test_single_rule_prices_an_hour_and_a_half_hour(): void
    {
        $unit = BookableUnit::factory()->create();
        $this->rule($unit, '16:00', '23:00', 100000);

        $this->assertSame(100000, $this->pricing->bookingBasePrice($unit, $this->local('2026-11-02 17:00'), $this->local('2026-11-02 18:00')));
        $this->assertSame(150000, $this->pricing->bookingBasePrice($unit, $this->local('2026-11-02 17:00'), $this->local('2026-11-02 18:30')));
    }

    public function test_booking_spanning_normal_and_peak_rules_is_split(): void
    {
        $unit = BookableUnit::factory()->create();
        $this->rule($unit, '16:00', '18:00', 50000);
        $this->rule($unit, '18:00', '23:00', 80000, ['is_peak' => true]);

        $this->assertSame(130000, $this->pricing->bookingBasePrice($unit, $this->local('2026-11-02 17:00'), $this->local('2026-11-02 19:00')));
        $this->assertSame(65000, $this->pricing->bookingBasePrice($unit, $this->local('2026-11-02 17:30'), $this->local('2026-11-02 18:30')));
    }

    public function test_the_sum_is_rounded_once_at_the_end(): void
    {
        $unit = BookableUnit::factory()->create();
        $this->rule($unit, '16:00', '23:00', 33333);

        $this->assertSame(50000, $this->pricing->bookingBasePrice($unit, $this->local('2026-11-02 17:00'), $this->local('2026-11-02 18:30')));
    }

    public function test_a_gap_between_rules_makes_the_price_unavailable(): void
    {
        $unit = BookableUnit::factory()->create();
        $this->rule($unit, '16:00', '18:00', 50000);
        $this->rule($unit, '19:00', '23:00', 80000);

        try {
            $this->pricing->bookingBasePrice($unit, $this->local('2026-11-02 17:00'), $this->local('2026-11-02 20:00'));
            $this->fail('A gap in the price rules must make the price unavailable.');
        } catch (PriceUnavailableException $exception) {
            $this->assertSame(__('pricing.no_price'), $exception->errors()['starts_at'][0]);
        }
    }

    public function test_an_interval_without_any_rule_is_unavailable(): void
    {
        $unit = BookableUnit::factory()->create();
        $this->rule($unit, '16:00', '23:00', 50000);

        $this->expectException(PriceUnavailableException::class);
        $this->pricing->bookingBasePrice($unit, $this->local('2026-11-02 08:00'), $this->local('2026-11-02 09:00'));
    }

    public function test_rules_of_other_units_are_ignored(): void
    {
        $unit = BookableUnit::factory()->create();
        $this->rule(BookableUnit::factory()->create(), '16:00', '23:00', 50000);

        $this->expectException(PriceUnavailableException::class);
        $this->pricing->bookingBasePrice($unit, $this->local('2026-11-02 17:00'), $this->local('2026-11-02 18:00'));
    }

    public function test_validity_dates_are_inclusive(): void
    {
        $unit = BookableUnit::factory()->create();
        $this->rule($unit, '16:00', '23:00', 70000, ['valid_from' => '2026-11-10', 'valid_to' => '2026-11-12']);

        foreach (['2026-11-10', '2026-11-12'] as $date) {
            $this->assertSame(70000, $this->pricing->bookingBasePrice($unit, $this->local("{$date} 17:00"), $this->local("{$date} 18:00")));
        }

        foreach (['2026-11-09', '2026-11-13'] as $date) {
            try {
                $this->pricing->bookingBasePrice($unit, $this->local("{$date} 17:00"), $this->local("{$date} 18:00"));
                $this->fail("{$date} is outside the validity period.");
            } catch (PriceUnavailableException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_weekday_specific_rule_applies_only_on_its_weekday(): void
    {
        $unit = BookableUnit::factory()->create();
        $this->rule($unit, '16:00', '23:00', 90000, ['weekday' => 1]);

        // 2026-11-02 is a Monday.
        $this->assertSame(90000, $this->pricing->bookingBasePrice($unit, $this->local('2026-11-02 17:00'), $this->local('2026-11-02 18:00')));

        $this->expectException(PriceUnavailableException::class);
        $this->pricing->bookingBasePrice($unit, $this->local('2026-11-03 17:00'), $this->local('2026-11-03 18:00'));
    }

    public function test_weekday_is_taken_from_the_local_date(): void
    {
        $unit = BookableUnit::factory()->create();
        $this->rule($unit, '00:00', '03:00', 40000, ['weekday' => 1]);

        // 2026-11-02 01:00 Damascus is still Sunday 22:00 UTC, but the local weekday (Monday) decides.
        $this->assertSame(40000, $this->pricing->bookingBasePrice($unit, $this->local('2026-11-02 01:00'), $this->local('2026-11-02 02:00')));
    }

    public function test_price_from_preloaded_rules_returns_null_when_uncovered(): void
    {
        $unit = BookableUnit::factory()->create();
        $this->rule($unit, '16:00', '18:00', 50000);
        $rules = $unit->priceRules()->get();

        $this->assertSame(50000, $this->pricing->priceFromRules($rules, $this->local('2026-11-02 17:00'), $this->local('2026-11-02 18:00')));
        $this->assertNull($this->pricing->priceFromRules($rules, $this->local('2026-11-02 17:00'), $this->local('2026-11-02 19:00')));
    }

    public function test_quote_booking_applies_the_best_discount(): void
    {
        $unit = BookableUnit::factory()->create();
        $this->rule($unit, '16:00', '23:00', 100000);
        Discount::factory()->create(['type' => 'percent', 'value' => 10]);

        $quote = $this->pricing->quoteBooking($unit, $this->local('2026-11-02 17:00'), $this->local('2026-11-02 18:00'), null, null);

        $this->assertInstanceOf(PriceQuote::class, $quote);
        $this->assertSame(100000, $quote->basePrice);
        $this->assertSame(10000, $quote->discountAmount);
        $this->assertSame(90000, $quote->finalPrice);
        $this->assertSame(['base_price', 'discount', 'discount_amount', 'final_price'], array_keys($quote->toArray()));
    }

    public function test_quote_subscription_uses_the_group_price_sport_and_academy(): void
    {
        $academy = Academy::factory()->create();
        $group = AcademyGroup::factory()->create(['academy_id' => $academy->id, 'monthly_price' => 200000]);
        $discount = Discount::factory()->create(['type' => 'percent', 'value' => 25, 'target' => 'subscription']);
        $discount->academies()->attach($academy);
        $discount->sports()->attach($academy->sport_id);
        Discount::factory()->create(['type' => 'percent', 'value' => 50, 'target' => 'booking']);

        $quote = $this->pricing->quoteSubscription($group, null);

        $this->assertSame(200000, $quote->basePrice);
        $this->assertSame($discount->id, $quote->discount?->id);
        $this->assertSame(50000, $quote->discountAmount);
        $this->assertSame(150000, $quote->finalPrice);
        $this->assertSame($discount->id, $quote->toArray()['discount']['id']);
    }
}
