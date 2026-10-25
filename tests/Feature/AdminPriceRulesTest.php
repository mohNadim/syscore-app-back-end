<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BookableUnit;
use App\Models\Permission;
use App\Models\PriceRule;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPriceRulesTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/admin/price-rules';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');
    }

    /**
     * Create a staff member whose role holds the given permission codes.
     *
     * @param  list<string>  $codes
     * @param  array<string, mixed>  $attributes
     */
    private function staffWithPermissions(array $codes, array $attributes = []): Staff
    {
        $role = Role::factory()->create(['name_ar' => 'دور '.fake()->unique()->numerify('####'), 'name_en' => 'Role']);

        foreach ($codes as $code) {
            $permission = Permission::query()->firstOrCreate(['code' => $code], ['description' => $code]);
            $role->permissions()->attach($permission);
        }

        return Staff::factory()->create(['role_id' => $role->id, ...$attributes]);
    }

    private function actingAsPricingManager(): Staff
    {
        $staff = $this->staffWithPermissions(['pricing.manage']);
        Sanctum::actingAs($staff);

        return $staff;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(BookableUnit $unit, array $overrides = []): array
    {
        return [
            'unit_id' => $unit->id,
            'weekday' => 2,
            'start_time' => '16:00',
            'end_time' => '18:00',
            'is_peak' => true,
            'price_per_hour' => 150000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rule(BookableUnit $unit, array $attributes = []): PriceRule
    {
        return PriceRule::factory()->create([
            'unit_id' => $unit->id,
            'weekday' => 2,
            'start_time' => '16:00:00',
            'end_time' => '18:00:00',
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            ...$attributes,
        ]);
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $rule = PriceRule::factory()->create();

        $endpoints = [
            ['getJson', self::URL],
            ['postJson', self::URL],
            ['getJson', self::URL."/{$rule->id}"],
            ['putJson', self::URL."/{$rule->id}"],
            ['deleteJson', self::URL."/{$rule->id}"],
        ];

        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertUnauthorized();
        }

        Sanctum::actingAs(User::factory()->create());
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }

        Sanctum::actingAs($this->staffWithPermissions(['hours.manage']));
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    public function test_store_creates_a_rule_and_audits(): void
    {
        $this->actingAsPricingManager();
        $unit = BookableUnit::factory()->create();

        $this->postJson(self::URL, $this->payload($unit))
            ->assertCreated()
            ->assertJsonPath('message', __('admin_price_rules.created', [], 'ar'))
            ->assertJsonPath('data.unit_id', $unit->id)
            ->assertJsonPath('data.unit.id', $unit->id)
            ->assertJsonPath('data.weekday', 2)
            ->assertJsonPath('data.start_time', '16:00')
            ->assertJsonPath('data.end_time', '18:00')
            ->assertJsonPath('data.is_peak', true)
            ->assertJsonPath('data.price_per_hour', 150000)
            ->assertJsonPath('data.valid_from', '2026-01-01')
            ->assertJsonPath('data.valid_to', '2026-12-31');

        $this->assertDatabaseHas('price_rules', ['unit_id' => $unit->id, 'weekday' => 2, 'start_time' => '16:00:00']);
        $this->assertTrue(AuditLog::query()->where('action', 'price_rule.created')->exists());
    }

    public function test_store_defaults_to_every_day_off_peak_and_open_ended(): void
    {
        $this->actingAsPricingManager();
        $unit = BookableUnit::factory()->create();

        $this->postJson(self::URL, [
            'unit_id' => $unit->id,
            'start_time' => '08:30',
            'end_time' => '12:00',
            'price_per_hour' => 0,
            'valid_from' => '2026-01-01',
        ])
            ->assertCreated()
            ->assertJsonPath('data.weekday', null)
            ->assertJsonPath('data.is_peak', false)
            ->assertJsonPath('data.valid_to', null)
            ->assertJsonPath('data.price_per_hour', 0);
    }

    public function test_show_update_and_destroy(): void
    {
        $this->actingAsPricingManager();
        $unit = BookableUnit::factory()->create();
        $rule = $this->rule($unit, ['price_per_hour' => 120000]);

        $this->getJson(self::URL."/{$rule->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $rule->id)
            ->assertJsonPath('data.unit.id', $unit->id);

        $this->putJson(self::URL."/{$rule->id}", ['price_per_hour' => 90000])
            ->assertOk()
            ->assertJsonPath('message', __('admin_price_rules.updated', [], 'ar'))
            ->assertJsonPath('data.price_per_hour', 90000)
            ->assertJsonPath('data.weekday', 2)
            ->assertJsonPath('data.start_time', '16:00');
        $this->assertTrue(AuditLog::query()->where('action', 'price_rule.updated')->exists());

        $this->deleteJson(self::URL."/{$rule->id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_price_rules.deleted', [], 'ar'));
        $this->assertDatabaseMissing('price_rules', ['id' => $rule->id]);
        $this->assertTrue(AuditLog::query()->where('action', 'price_rule.deleted')->exists());
    }

    public function test_missing_ids_return_not_found(): void
    {
        $this->actingAsPricingManager();

        $this->getJson(self::URL.'/999')->assertNotFound();
        $this->putJson(self::URL.'/999', ['price_per_hour' => 1])->assertNotFound();
        $this->deleteJson(self::URL.'/999')->assertNotFound();
    }

    public function test_store_validation_messages_in_arabic_and_english(): void
    {
        $this->actingAsPricingManager();

        $this->postJson(self::URL, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'unit_id' => __('admin_price_rules.validation.unit_id_invalid', [], 'ar'),
                'start_time' => __('admin_price_rules.validation.start_time_invalid', [], 'ar'),
                'end_time' => __('admin_price_rules.validation.end_time_invalid', [], 'ar'),
                'price_per_hour' => __('admin_price_rules.validation.price_invalid', [], 'ar'),
                'valid_from' => __('admin_price_rules.validation.valid_from_invalid', [], 'ar'),
            ]);

        $this->withHeader('Accept-Language', 'en')
            ->postJson(self::URL, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'unit_id' => __('admin_price_rules.validation.unit_id_invalid', [], 'en'),
                'price_per_hour' => __('admin_price_rules.validation.price_invalid', [], 'en'),
            ]);
    }

    public function test_store_rejects_wrong_types_and_out_of_range_values(): void
    {
        $this->actingAsPricingManager();
        $unit = BookableUnit::factory()->create();

        $this->postJson(self::URL, $this->payload($unit, [
            'unit_id' => 9999,
            'weekday' => 7,
            'start_time' => '4pm',
            'end_time' => 'x',
            'is_peak' => 'maybe',
            'price_per_hour' => 'free',
            'valid_from' => '01/01/2026',
            'valid_to' => 'never',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unit_id', 'weekday', 'start_time', 'end_time', 'is_peak', 'price_per_hour', 'valid_from', 'valid_to']);

        $this->postJson(self::URL, $this->payload($unit, ['price_per_hour' => -1]))->assertJsonValidationErrors(['price_per_hour']);
        $this->postJson(self::URL, $this->payload($unit, ['price_per_hour' => 100000001]))->assertJsonValidationErrors(['price_per_hour']);
        $this->postJson(self::URL, $this->payload($unit, ['price_per_hour' => 1.5]))->assertJsonValidationErrors(['price_per_hour']);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $this->actingAsPricingManager();
        $unit = BookableUnit::factory()->create();

        $this->postJson(self::URL, $this->payload($unit, ['start_time' => '18:00', 'end_time' => '18:00']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_time' => __('admin_price_rules.validation.end_after_start', [], 'ar')]);

        $this->postJson(self::URL, $this->payload($unit, ['start_time' => '22:00', 'end_time' => '02:00']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_time']);
    }

    public function test_times_must_be_multiples_of_thirty_minutes(): void
    {
        $this->actingAsPricingManager();
        $unit = BookableUnit::factory()->create();

        $this->postJson(self::URL, $this->payload($unit, ['start_time' => '16:15']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time' => __('admin_price_rules.validation.time_step', [], 'ar')]);

        $this->withHeader('Accept-Language', 'en')
            ->postJson(self::URL, $this->payload($unit, ['end_time' => '18:45']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_time' => __('admin_price_rules.validation.time_step', [], 'en')]);

        $this->postJson(self::URL, $this->payload($unit, ['start_time' => '16:30', 'end_time' => '18:30']))->assertCreated();
    }

    public function test_valid_to_cannot_precede_valid_from(): void
    {
        $this->actingAsPricingManager();
        $unit = BookableUnit::factory()->create();

        $this->postJson(self::URL, $this->payload($unit, ['valid_from' => '2026-06-01', 'valid_to' => '2026-05-31']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['valid_to' => __('admin_price_rules.validation.valid_to_after_from', [], 'ar')]);

        $this->postJson(self::URL, $this->payload($unit, ['valid_from' => '2026-06-01', 'valid_to' => '2026-06-01']))->assertCreated();
    }

    public function test_overlapping_times_on_the_same_weekday_are_rejected(): void
    {
        $this->actingAsPricingManager();
        $unit = BookableUnit::factory()->create();
        $existing = $this->rule($unit);

        $this->postJson(self::URL, $this->payload($unit, ['start_time' => '17:00', 'end_time' => '20:00']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time' => __('admin_price_rules.validation.overlap', ['id' => $existing->id], 'ar')]);

        $this->withHeader('Accept-Language', 'en')
            ->postJson(self::URL, $this->payload($unit, ['start_time' => '16:00', 'end_time' => '17:00']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time' => __('admin_price_rules.validation.overlap', ['id' => $existing->id], 'en')]);

        $this->assertSame(1, PriceRule::query()->count());
    }

    public function test_touching_times_and_other_weekdays_do_not_overlap(): void
    {
        $this->actingAsPricingManager();
        $unit = BookableUnit::factory()->create();
        $this->rule($unit);

        $this->postJson(self::URL, $this->payload($unit, ['start_time' => '18:00', 'end_time' => '22:00']))->assertCreated();
        $this->postJson(self::URL, $this->payload($unit, ['start_time' => '14:00', 'end_time' => '16:00']))->assertCreated();
        $this->postJson(self::URL, $this->payload($unit, ['weekday' => 4]))->assertCreated();
    }

    public function test_every_day_rule_overlaps_a_weekday_rule_in_both_directions(): void
    {
        $this->actingAsPricingManager();
        $unit = BookableUnit::factory()->create();
        $weekdayRule = $this->rule($unit, ['weekday' => 3, 'start_time' => '16:00:00', 'end_time' => '20:00:00']);

        $this->postJson(self::URL, $this->payload($unit, ['weekday' => null, 'start_time' => '19:00', 'end_time' => '21:00']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time' => __('admin_price_rules.validation.overlap', ['id' => $weekdayRule->id], 'ar')]);

        $weekdayRule->delete();
        $this->rule($unit, ['weekday' => null, 'start_time' => '16:00:00', 'end_time' => '20:00:00']);

        $this->postJson(self::URL, $this->payload($unit, ['weekday' => 3, 'start_time' => '19:00', 'end_time' => '21:00']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time']);
    }

    public function test_different_units_never_overlap(): void
    {
        $this->actingAsPricingManager();
        $this->rule(BookableUnit::factory()->create());

        $this->postJson(self::URL, $this->payload(BookableUnit::factory()->create()))->assertCreated();
    }

    public function test_validity_periods_that_do_not_intersect_are_allowed(): void
    {
        $this->actingAsPricingManager();
        $unit = BookableUnit::factory()->create();
        $this->rule($unit, ['valid_from' => '2026-01-01', 'valid_to' => '2026-12-31']);

        $this->postJson(self::URL, $this->payload($unit, ['valid_from' => '2027-01-01', 'valid_to' => null]))->assertCreated();
        $this->postJson(self::URL, $this->payload($unit, ['valid_from' => '2025-01-01', 'valid_to' => '2025-12-31']))->assertCreated();
        $this->postJson(self::URL, $this->payload($unit, ['valid_from' => '2026-12-31', 'valid_to' => '2026-12-31']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time']);
    }

    public function test_open_ended_rule_overlaps_a_later_period(): void
    {
        $this->actingAsPricingManager();
        $unit = BookableUnit::factory()->create();
        $this->rule($unit, ['valid_from' => '2026-01-01', 'valid_to' => null]);

        $this->postJson(self::URL, $this->payload($unit, ['valid_from' => '2030-01-01', 'valid_to' => '2030-12-31']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time']);

        $this->postJson(self::URL, $this->payload($unit, ['valid_from' => '2025-01-01', 'valid_to' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time']);
    }

    public function test_update_does_not_conflict_with_itself(): void
    {
        $this->actingAsPricingManager();
        $unit = BookableUnit::factory()->create();
        $rule = $this->rule($unit);

        $this->putJson(self::URL."/{$rule->id}", [])->assertOk();
        $this->putJson(self::URL."/{$rule->id}", $this->payload($unit, ['is_peak' => $rule->is_peak, 'price_per_hour' => $rule->price_per_hour]))->assertOk();
        $this->assertSame(0, AuditLog::query()->where('action', 'price_rule.updated')->count());
    }

    public function test_update_that_creates_an_overlap_is_rejected(): void
    {
        $this->actingAsPricingManager();
        $unit = BookableUnit::factory()->create();
        $this->rule($unit);
        $other = $this->rule($unit, ['start_time' => '18:00:00', 'end_time' => '20:00:00']);

        $this->putJson(self::URL."/{$other->id}", ['start_time' => '17:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time']);

        $this->putJson(self::URL."/{$other->id}", ['weekday' => null, 'start_time' => '17:30'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time']);

        $this->rule($unit, ['weekday' => 5]);
        $this->putJson(self::URL."/{$other->id}", ['weekday' => 5, 'start_time' => '17:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time']);
    }

    public function test_update_checks_merged_values_for_order(): void
    {
        $this->actingAsPricingManager();
        $unit = BookableUnit::factory()->create();
        $rule = $this->rule($unit);

        $this->putJson(self::URL."/{$rule->id}", ['end_time' => '15:30'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_time' => __('admin_price_rules.validation.end_after_start', [], 'ar')]);

        $this->putJson(self::URL."/{$rule->id}", ['start_time' => '19:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_time']);

        $this->putJson(self::URL."/{$rule->id}", ['valid_from' => '2027-01-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['valid_to' => __('admin_price_rules.validation.valid_to_after_from', [], 'ar')]);

        $this->putJson(self::URL."/{$rule->id}", ['start_time' => '16:10'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time']);

        $this->putJson(self::URL."/{$rule->id}", ['weekday' => null, 'valid_to' => null, 'is_peak' => true])
            ->assertOk()
            ->assertJsonPath('data.weekday', null)
            ->assertJsonPath('data.valid_to', null)
            ->assertJsonPath('data.is_peak', true);
    }

    public function test_index_is_ordered_and_filterable(): void
    {
        $this->actingAsPricingManager();
        $unitA = BookableUnit::factory()->create();
        $unitB = BookableUnit::factory()->create();

        $everyDay = $this->rule($unitA, ['weekday' => null, 'start_time' => '10:00:00', 'end_time' => '12:00:00', 'valid_from' => '2025-01-01', 'valid_to' => null]);
        $wednesdayLate = $this->rule($unitA, ['weekday' => 3, 'start_time' => '18:00:00', 'end_time' => '20:00:00', 'is_peak' => true]);
        $wednesdayEarly = $this->rule($unitA, ['weekday' => 3, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'valid_from' => '2025-01-01', 'valid_to' => '2025-12-31']);
        $sunday = $this->rule($unitA, ['weekday' => 0, 'start_time' => '08:00:00', 'end_time' => '10:00:00']);
        $otherUnit = $this->rule($unitB, ['weekday' => 1]);

        $ids = fn ($response) => collect($response->json('data'))->pluck('id')->all();

        $all = $this->getJson(self::URL)->assertOk();
        $this->assertSame(
            [$everyDay->id, $sunday->id, $wednesdayEarly->id, $wednesdayLate->id, $otherUnit->id],
            $ids($all),
        );
        $all->assertJsonPath('data.0.unit.id', $unitA->id);

        $this->assertSame(
            [$everyDay->id, $wednesdayEarly->id, $wednesdayLate->id],
            $ids($this->getJson(self::URL."?unit_id={$unitA->id}&weekday=3")),
        );
        $this->assertSame([$wednesdayLate->id], $ids($this->getJson(self::URL.'?is_peak=1')));
        $this->assertSame(
            [$everyDay->id, $wednesdayEarly->id],
            $ids($this->getJson(self::URL.'?active_on=2025-06-15&unit_id='.$unitA->id)),
        );
        $this->assertSame(
            [$everyDay->id, $sunday->id, $wednesdayLate->id, $otherUnit->id],
            $ids($this->getJson(self::URL.'?active_on=2026-03-01')),
        );
        $this->assertSame([$everyDay->id], $ids($this->getJson(self::URL.'?active_on=2027-03-01&unit_id='.$unitA->id)));
    }

    public function test_index_rejects_invalid_filters(): void
    {
        $this->actingAsPricingManager();

        $this->getJson(self::URL.'?weekday=9&unit_id=x&is_peak=maybe&active_on=tomorrow')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['weekday', 'unit_id', 'is_peak', 'active_on']);

        $this->withHeader('Accept-Language', 'en')
            ->getJson(self::URL.'?active_on=tomorrow')
            ->assertJsonValidationErrors(['active_on' => __('admin_price_rules.validation.date_invalid', [], 'en')]);
    }
}
