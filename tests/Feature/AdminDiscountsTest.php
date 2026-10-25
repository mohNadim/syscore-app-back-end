<?php

namespace Tests\Feature;

use App\Models\Academy;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Discount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sport;
use App\Models\Staff;
use App\Models\User;
use App\Models\UserCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDiscountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');
    }

    /**
     * @param  list<string>  $codes
     */
    private function staffWithPermissions(array $codes): Staff
    {
        $role = Role::factory()->create(['name_ar' => 'دور '.fake()->unique()->numerify('####'), 'name_en' => 'Role']);

        foreach ($codes as $code) {
            $permission = Permission::query()->firstOrCreate(['code' => $code], ['description' => $code]);
            $role->permissions()->attach($permission);
        }

        return Staff::factory()->create(['role_id' => $role->id]);
    }

    private function actingAsManager(): Staff
    {
        $staff = $this->staffWithPermissions(['discounts.manage']);
        Sanctum::actingAs($staff);

        return $staff;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name_ar' => 'خصم الصيف',
            'name_en' => 'Summer',
            'type' => 'percent',
            'value' => 20,
            'starts_at' => '2026-11-01 00:00',
            'ends_at' => '2026-11-30 23:59',
            ...$overrides,
        ];
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $discount = Discount::factory()->create();

        $endpoints = [
            ['getJson', '/api/v1/admin/discounts'],
            ['postJson', '/api/v1/admin/discounts'],
            ['getJson', "/api/v1/admin/discounts/{$discount->id}"],
            ['putJson', "/api/v1/admin/discounts/{$discount->id}"],
            ['deleteJson', "/api/v1/admin/discounts/{$discount->id}"],
        ];

        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertUnauthorized();
        }

        Sanctum::actingAs(User::factory()->create());
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }

        Sanctum::actingAs($this->staffWithPermissions(['sports.manage']));
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    public function test_weekday_mask_helpers(): void
    {
        $this->assertNull(Discount::weekdaysToMask(null));
        $this->assertNull(Discount::weekdaysToMask([]));
        $this->assertSame(0b1000001, Discount::weekdaysToMask([0, 6]));

        $discount = new Discount(['weekdays' => Discount::weekdaysToMask([5, 0, 3])]);
        $this->assertSame([0, 3, 5], $discount->weekdayList());
        $this->assertTrue($discount->appliesOnWeekday(3));
        $this->assertFalse($discount->appliesOnWeekday(4));

        $everyDay = new Discount(['weekdays' => null]);
        $this->assertNull($everyDay->weekdayList());
        $this->assertTrue($everyDay->appliesOnWeekday(2));
    }

    public function test_create_discount_with_all_restrictions(): void
    {
        $staff = $this->actingAsManager();
        $category = UserCategory::factory()->create();
        $sport = Sport::factory()->create();
        $academy = Academy::factory()->create();

        $response = $this->postJson('/api/v1/admin/discounts', $this->payload([
            'target' => 'booking',
            'time_from' => '10:00',
            'time_to' => '14:00',
            'weekdays' => [5, 1],
            'notify_users' => true,
            'category_ids' => [$category->id],
            'sport_ids' => [$sport->id],
            'academy_ids' => [$academy->id],
        ]))
            ->assertCreated()
            ->assertJsonPath('message', __('admin_discounts.created', [], 'ar'))
            ->assertJsonPath('data.type', 'percent')
            ->assertJsonPath('data.value', 20)
            ->assertJsonPath('data.target', 'booking')
            ->assertJsonPath('data.starts_at', '2026-11-01T00:00:00+03:00')
            ->assertJsonPath('data.time_from', '10:00')
            ->assertJsonPath('data.time_to', '14:00')
            ->assertJsonPath('data.weekdays', [1, 5])
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.notify_users', true)
            ->assertJsonPath('data.categories.0.id', $category->id)
            ->assertJsonPath('data.sports.0.name_ar', $sport->name_ar)
            ->assertJsonPath('data.academies.0.id', $academy->id)
            ->assertJsonPath('data.created_by_staff_id', $staff->id);

        $id = $response->json('data.id');
        $discount = Discount::findOrFail($id);
        $this->assertSame(0b100010, $discount->weekdays);
        $this->assertSame('2026-10-31 21:00:00', $discount->starts_at->format('Y-m-d H:i:s'));
        $this->assertTrue(AuditLog::query()->where('action', 'discount.created')->where('entity_id', $id)->exists());
    }

    public function test_create_applies_defaults(): void
    {
        $this->actingAsManager();

        $this->postJson('/api/v1/admin/discounts', $this->payload(['type' => 'fixed', 'value' => 5000]))
            ->assertCreated()
            ->assertJsonPath('data.target', 'all')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.notify_users', false)
            ->assertJsonPath('data.weekdays', null)
            ->assertJsonPath('data.time_from', null)
            ->assertJsonPath('data.categories', []);
    }

    public function test_validation_errors_in_both_languages(): void
    {
        $this->actingAsManager();

        $this->postJson('/api/v1/admin/discounts', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name_ar.0', __('admin_discounts.validation.name_ar_required', [], 'ar'))
            ->assertJsonPath('errors.type.0', __('admin_discounts.validation.type_invalid', [], 'ar'))
            ->assertJsonPath('errors.value.0', __('admin_discounts.validation.value_invalid', [], 'ar'))
            ->assertJsonPath('errors.starts_at.0', __('admin_discounts.validation.starts_at_invalid', [], 'ar'))
            ->assertJsonPath('errors.ends_at.0', __('admin_discounts.validation.ends_at_invalid', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/discounts', $this->payload([
                'name_ar' => str_repeat('a', 151),
                'target' => 'nobody',
                'ends_at' => '2026-10-01 00:00',
                'time_from' => '10:00',
                'weekdays' => [1, 1, 9],
                'is_active' => 'maybe',
                'category_ids' => [999999],
                'sport_ids' => 'x',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.name_ar.0', 'The Arabic name must be text of at most 150 characters.')
            ->assertJsonPath('errors.target.0', 'The target must be booking, subscription or all.')
            ->assertJsonPath('errors.ends_at.0', 'The end must be later than the start.')
            ->assertJsonPath('errors.time_to.0', 'The daily window needs both a start and an end time, or neither.')
            ->assertJsonPath('errors.is_active.0', 'The active flag must be true or false.')
            ->assertJsonValidationErrors(['weekdays.0', 'weekdays.1', 'weekdays.2', 'category_ids.0', 'sport_ids']);
    }

    public function test_value_ranges_depend_on_the_type(): void
    {
        $this->actingAsManager();

        $this->postJson('/api/v1/admin/discounts', $this->payload(['value' => 101]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.value.0', __('admin_discounts.validation.value_percent_range', [], 'ar'));
        $this->postJson('/api/v1/admin/discounts', $this->payload(['value' => 100]))->assertCreated();

        $this->postJson('/api/v1/admin/discounts', $this->payload(['type' => 'fixed', 'value' => 100000001]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.value.0', __('admin_discounts.validation.value_fixed_range', [], 'ar'));
        $this->postJson('/api/v1/admin/discounts', $this->payload(['type' => 'fixed', 'value' => 100000000]))->assertCreated();

        $this->postJson('/api/v1/admin/discounts', $this->payload(['value' => 0]))->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->postJson('/api/v1/admin/discounts', $this->payload(['value' => 'abc']))->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->postJson('/api/v1/admin/discounts', $this->payload(['value' => 10.5]))->assertUnprocessable()->assertJsonValidationErrors('value');
    }

    public function test_time_window_rules(): void
    {
        $this->actingAsManager();

        $this->postJson('/api/v1/admin/discounts', $this->payload(['time_to' => '10:00']))
            ->assertUnprocessable()->assertJsonPath('errors.time_from.0', __('admin_discounts.validation.time_window_incomplete', [], 'ar'));
        $this->postJson('/api/v1/admin/discounts', $this->payload(['time_from' => '14:00', 'time_to' => '10:00']))
            ->assertUnprocessable()->assertJsonPath('errors.time_to.0', __('admin_discounts.validation.time_to_after_from', [], 'ar'));
        $this->postJson('/api/v1/admin/discounts', $this->payload(['time_from' => '25:00', 'time_to' => '26:00']))
            ->assertUnprocessable()->assertJsonValidationErrors(['time_from', 'time_to']);
    }

    public function test_update_is_partial_validates_against_stored_values_and_syncs_restrictions(): void
    {
        $this->actingAsManager();
        $oldCategory = UserCategory::factory()->create();
        $newCategory = UserCategory::factory()->create();
        $discount = Discount::factory()->create([
            'type' => 'percent',
            'value' => 30,
            'starts_at' => '2026-11-10 10:00:00',
            'ends_at' => '2026-11-20 10:00:00',
            'time_from' => '10:00:00',
            'time_to' => '12:00:00',
            'weekdays' => 1,
        ]);
        $discount->categories()->attach($oldCategory);
        $url = "/api/v1/admin/discounts/{$discount->id}";

        $this->putJson($url, ['name_en' => 'Renamed'])->assertOk()->assertJsonPath('data.name_en', 'Renamed')->assertJsonPath('data.value', 30)->assertJsonPath('data.categories.0.id', $oldCategory->id);

        $this->putJson($url, ['type' => 'fixed'])->assertOk()->assertJsonPath('data.type', 'fixed');
        $this->putJson($url, ['type' => 'percent', 'value' => 150])->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->putJson($url, ['value' => 150000000])->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->putJson($url, ['value' => 500])->assertOk();
        $this->putJson($url, ['type' => 'percent'])->assertUnprocessable()->assertJsonValidationErrors('value');

        $this->putJson($url, ['ends_at' => '2026-11-05 10:00'])->assertUnprocessable()->assertJsonValidationErrors('ends_at');
        $this->putJson($url, ['starts_at' => '2026-12-01 10:00'])->assertUnprocessable()->assertJsonValidationErrors('ends_at');
        $this->putJson($url, ['time_to' => '09:00'])->assertUnprocessable()->assertJsonPath('errors.time_to.0', __('admin_discounts.validation.time_to_after_from', [], 'ar'));
        $this->putJson($url, ['time_from' => null])->assertUnprocessable()->assertJsonValidationErrors('time_from');

        $this->putJson($url, ['time_from' => null, 'time_to' => null, 'weekdays' => [], 'category_ids' => [$newCategory->id], 'sport_ids' => null])
            ->assertOk()
            ->assertJsonPath('data.time_from', null)
            ->assertJsonPath('data.weekdays', null)
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.categories.0.id', $newCategory->id);

        $this->assertNull($discount->fresh()->weekdays);
        $this->assertTrue(AuditLog::query()->where('action', 'discount.updated')->where('entity_id', $discount->id)->exists());
    }

    public function test_update_without_changes_writes_no_audit_row(): void
    {
        $this->actingAsManager();
        $discount = Discount::factory()->create(['name_ar' => 'ثابت']);

        $this->putJson("/api/v1/admin/discounts/{$discount->id}", ['name_ar' => 'ثابت'])->assertOk();

        $this->assertFalse(AuditLog::query()->where('action', 'discount.updated')->exists());
    }

    public function test_index_filters_orders_and_paginates(): void
    {
        $this->actingAsManager();
        $old = Discount::factory()->create(['starts_at' => '2026-01-01 00:00:00', 'ends_at' => '2026-01-31 00:00:00', 'target' => 'booking']);
        $current = Discount::factory()->create(['starts_at' => '2026-03-01 00:00:00', 'ends_at' => '2026-03-31 00:00:00', 'is_active' => false]);
        $latest = Discount::factory()->create(['starts_at' => '2026-05-01 00:00:00', 'ends_at' => '2026-05-31 00:00:00']);

        $this->getJson('/api/v1/admin/discounts')
            ->assertOk()
            ->assertJsonPath('data.0.id', $latest->id)
            ->assertJsonPath('data.1.id', $current->id)
            ->assertJsonPath('data.2.id', $old->id)
            ->assertJsonPath('meta.total', 3);

        $this->getJson('/api/v1/admin/discounts?is_active=0')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $current->id);
        $this->getJson('/api/v1/admin/discounts?target=booking')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $old->id);
        $this->getJson('/api/v1/admin/discounts?active_at='.urlencode('2026-03-15 12:00'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $current->id);
        $this->getJson('/api/v1/admin/discounts?per_page=2')->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/admin/discounts?per_page=500')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/admin/discounts?active_at=yesterday')->assertUnprocessable()->assertJsonValidationErrors('active_at');
        $this->getJson('/api/v1/admin/discounts?target=x')->assertUnprocessable()->assertJsonValidationErrors('target');
    }

    public function test_delete_detaches_restrictions_but_is_blocked_when_used(): void
    {
        $this->actingAsManager();
        $category = UserCategory::factory()->create();
        $sport = Sport::factory()->create();
        $free = Discount::factory()->create();
        $free->categories()->attach($category);
        $free->sports()->attach($sport);

        $this->deleteJson("/api/v1/admin/discounts/{$free->id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_discounts.deleted', [], 'ar'));
        $this->assertDatabaseMissing('discounts', ['id' => $free->id]);
        $this->assertDatabaseCount('discount_categories', 0);
        $this->assertDatabaseCount('discount_sports', 0);
        $this->assertTrue(AuditLog::query()->where('action', 'discount.deleted')->where('entity_id', $free->id)->exists());

        $used = Discount::factory()->create();
        Booking::factory()->create(['discount_id' => $used->id]);

        $this->deleteJson("/api/v1/admin/discounts/{$used->id}")
            ->assertConflict()
            ->assertJsonPath('message', __('admin_discounts.discount_in_use', [], 'ar'));
        $this->assertDatabaseHas('discounts', ['id' => $used->id]);

        $this->deleteJson('/api/v1/admin/discounts/999999')->assertNotFound();
    }
}
