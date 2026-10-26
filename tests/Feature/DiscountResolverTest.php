<?php

namespace Tests\Feature;

use App\Models\Academy;
use App\Models\Discount;
use App\Models\Sport;
use App\Models\User;
use App\Models\UserCategory;
use App\Services\Pricing\DiscountResolver;
use App\Services\Pricing\DiscountSelectionStrategy;
use App\Services\Pricing\HighestDiscountStrategy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DiscountResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Monday 2026-11-02 11:00 in Damascus (UTC+3).
        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00:00', 'UTC'));
    }

    private function resolver(): DiscountResolver
    {
        return app(DiscountResolver::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function discount(array $attributes = []): Discount
    {
        return Discount::factory()->create([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            ...$attributes,
        ]);
    }

    /**
     * @return list<int>
     */
    private function eligibleIds(string $target = 'booking', ?User $user = null, ?int $sportId = null, ?int $academyId = null): array
    {
        return $this->resolver()->eligible($target, $user, $sportId, $academyId)->pluck('id')->all();
    }

    public function test_default_strategy_is_bound(): void
    {
        $this->assertInstanceOf(HighestDiscountStrategy::class, app(DiscountSelectionStrategy::class));
    }

    public function test_inactive_discounts_are_ignored(): void
    {
        $this->discount(['is_active' => false]);
        $active = $this->discount();

        $this->assertSame([$active->id], $this->eligibleIds());
    }

    public function test_validity_period_is_checked_at_the_given_moment(): void
    {
        $this->discount(['starts_at' => now()->addMinute(), 'ends_at' => now()->addDay()]);
        $this->discount(['starts_at' => now()->subDay(), 'ends_at' => now()->subMinute()]);
        $valid = $this->discount();

        $this->assertSame([$valid->id], $this->eligibleIds());
        $this->assertSame([], $this->resolver()->eligible('booking', null, null, null, now()->addDays(10))->pluck('id')->all());
    }

    public function test_target_must_match_or_be_all(): void
    {
        $booking = $this->discount(['target' => 'booking']);
        $subscription = $this->discount(['target' => 'subscription']);
        $all = $this->discount(['target' => 'all']);

        $this->assertSame([$booking->id, $all->id], $this->eligibleIds('booking'));
        $this->assertSame([$subscription->id, $all->id], $this->eligibleIds('subscription'));
    }

    public function test_daily_time_window_uses_the_local_time_of_creation_and_excludes_the_end(): void
    {
        $inside = $this->discount(['time_from' => '10:00', 'time_to' => '12:00']);
        $this->discount(['time_from' => '12:00', 'time_to' => '14:00']);
        $this->discount(['time_from' => '09:00', 'time_to' => '11:00']);
        $startsNow = $this->discount(['time_from' => '11:00', 'time_to' => '11:30']);

        $this->assertSame([$inside->id, $startsNow->id], $this->eligibleIds());
    }

    public function test_weekday_restriction_uses_the_local_weekday_of_creation(): void
    {
        // Monday = 1.
        $monday = $this->discount(['weekdays' => Discount::weekdaysToMask([1, 3])]);
        $this->discount(['weekdays' => Discount::weekdaysToMask([0, 6])]);
        $everyDay = $this->discount(['weekdays' => null]);

        $this->assertSame([$monday->id, $everyDay->id], $this->eligibleIds());
    }

    public function test_category_restriction_needs_an_active_membership(): void
    {
        $student = UserCategory::factory()->create(['is_active' => true]);
        $retired = UserCategory::factory()->create(['is_active' => false]);
        $discount = $this->discount();
        $discount->categories()->attach([$student->id, $retired->id]);

        $member = User::factory()->create();
        $member->categories()->attach($student);
        $inactiveOnly = User::factory()->create();
        $inactiveOnly->categories()->attach($retired);
        $outsider = User::factory()->create();

        $this->assertSame([$discount->id], $this->eligibleIds('booking', $member));
        $this->assertSame([], $this->eligibleIds('booking', $inactiveOnly));
        $this->assertSame([], $this->eligibleIds('booking', $outsider));
        $this->assertSame([], $this->eligibleIds('booking', null));
    }

    public function test_discount_without_categories_is_open_to_guests(): void
    {
        $open = $this->discount();

        $this->assertSame([$open->id], $this->eligibleIds('booking', null));
    }

    public function test_sport_restriction(): void
    {
        $football = Sport::factory()->create();
        $swimming = Sport::factory()->create();
        $discount = $this->discount();
        $discount->sports()->attach($football);

        $this->assertSame([$discount->id], $this->eligibleIds('booking', null, $football->id));
        $this->assertSame([], $this->eligibleIds('booking', null, $swimming->id));
        $this->assertSame([], $this->eligibleIds('booking', null, null));
    }

    public function test_academy_restriction(): void
    {
        $academy = Academy::factory()->create();
        $other = Academy::factory()->create();
        $discount = $this->discount();
        $discount->academies()->attach($academy);

        $this->assertSame([$discount->id], $this->eligibleIds('subscription', null, null, $academy->id));
        $this->assertSame([], $this->eligibleIds('subscription', null, null, $other->id));
        $this->assertSame([], $this->eligibleIds('booking', null, null, null));
    }

    public function test_all_conditions_must_hold_together(): void
    {
        $category = UserCategory::factory()->create();
        $sport = Sport::factory()->create();
        $discount = $this->discount(['target' => 'booking', 'time_from' => '10:00', 'time_to' => '12:00', 'weekdays' => Discount::weekdaysToMask([1])]);
        $discount->categories()->attach($category);
        $discount->sports()->attach($sport);

        $user = User::factory()->create();
        $user->categories()->attach($category);

        $this->assertSame([$discount->id], $this->eligibleIds('booking', $user, $sport->id));
        $this->assertSame([], $this->eligibleIds('booking', $user, null));
        $this->assertSame([], $this->eligibleIds('booking', User::factory()->create(), $sport->id));
        $this->assertSame([], $this->eligibleIds('subscription', $user, $sport->id));
    }

    public function test_amounts_for_percent_and_fixed_discounts(): void
    {
        $percent = $this->discount(['type' => 'percent', 'value' => 15]);
        $fixed = $this->discount(['type' => 'fixed', 'value' => 20000]);

        $this->assertSame(15000, $this->resolver()->amountFor($percent, 100000));
        $this->assertSame(5, $this->resolver()->amountFor($this->discount(['type' => 'percent', 'value' => 10]), 45));
        $this->assertSame(20000, $this->resolver()->amountFor($fixed, 100000));
        $this->assertSame(8000, $this->resolver()->amountFor($fixed, 8000));
    }

    public function test_highest_amount_wins(): void
    {
        $this->discount(['type' => 'percent', 'value' => 10]);
        $best = $this->discount(['type' => 'fixed', 'value' => 30000]);
        $this->discount(['type' => 'percent', 'value' => 20]);

        $quote = $this->resolver()->resolve('booking', 100000, null, null, null);

        $this->assertSame($best->id, $quote->discount?->id);
        $this->assertSame(30000, $quote->discountAmount);
        $this->assertSame(70000, $quote->finalPrice);
    }

    public function test_tie_goes_to_the_lowest_discount_id(): void
    {
        $first = $this->discount(['type' => 'percent', 'value' => 10]);
        $this->discount(['type' => 'fixed', 'value' => 10000]);

        $quote = $this->resolver()->resolve('booking', 100000, null, null, null);

        $this->assertSame($first->id, $quote->discount?->id);
        $this->assertSame(10000, $quote->discountAmount);
    }

    public function test_no_discount_leaves_the_price_untouched(): void
    {
        $this->discount(['is_active' => false]);

        $quote = $this->resolver()->resolve('booking', 100000, null, null, null);

        $this->assertNull($quote->discount);
        $this->assertSame(0, $quote->discountAmount);
        $this->assertSame(100000, $quote->finalPrice);
        $this->assertNull($quote->toArray()['discount']);
    }

    public function test_a_free_price_cannot_go_negative(): void
    {
        $this->discount(['type' => 'fixed', 'value' => 50000]);

        $quote = $this->resolver()->resolve('booking', 0, null, null, null);

        $this->assertNull($quote->discount);
        $this->assertSame(0, $quote->finalPrice);
    }

    public function test_a_discount_never_exceeds_the_price(): void
    {
        $this->discount(['type' => 'fixed', 'value' => 50000]);

        $quote = $this->resolver()->resolve('booking', 30000, null, null, null);

        $this->assertSame(30000, $quote->discountAmount);
        $this->assertSame(0, $quote->finalPrice);
    }

    public function test_the_selection_strategy_can_be_swapped_through_the_container(): void
    {
        $this->discount(['type' => 'percent', 'value' => 10]);
        $this->discount(['type' => 'percent', 'value' => 50]);
        $this->discount(['type' => 'percent', 'value' => 30]);

        $this->app->bind(DiscountSelectionStrategy::class, fn () => new class implements DiscountSelectionStrategy
        {
            public function choose(Collection $candidates, int $basePrice): ?array
            {
                return $candidates->sortBy('amount')->first();
            }
        });

        $quote = app(DiscountResolver::class)->resolve('booking', 100000, null, null, null);

        $this->assertSame(10000, $quote->discountAmount);
    }

    public function test_a_strategy_may_decline_to_apply_any_discount(): void
    {
        $this->discount();

        $this->app->bind(DiscountSelectionStrategy::class, fn () => new class implements DiscountSelectionStrategy
        {
            public function choose(Collection $candidates, int $basePrice): ?array
            {
                return null;
            }
        });

        $quote = app(DiscountResolver::class)->resolve('booking', 100000, null, null, null);

        $this->assertNull($quote->discount);
        $this->assertSame(100000, $quote->finalPrice);
    }
}
