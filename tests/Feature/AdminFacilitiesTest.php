<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BookableUnit;
use App\Models\Facility;
use App\Models\Permission;
use App\Models\PriceRule;
use App\Models\Role;
use App\Models\Space;
use App\Models\Sport;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminFacilitiesTest extends TestCase
{
    use RefreshDatabase;

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

    private function actingAsFacilitiesManager(): Staff
    {
        $staff = $this->staffWithPermissions(['facilities.manage']);
        Sanctum::actingAs($staff);

        return $staff;
    }

    /**
     * @return array<string, mixed>
     */
    private function unitPayload(Facility $facility, array|string $spaceIds, array $overrides = []): array
    {
        return [
            'facility_id' => $facility->id,
            'code' => 'PITCH_A',
            'name_ar' => 'ملعب أ',
            'name_en' => 'Pitch A',
            'space_ids' => $spaceIds,
            ...$overrides,
        ];
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $facility = Facility::factory()->create();
        $space = Space::factory()->for($facility)->create();
        $unit = BookableUnit::factory()->for($facility)->create();

        $endpoints = [
            ['getJson', '/api/v1/admin/facilities'],
            ['postJson', '/api/v1/admin/facilities'],
            ['getJson', "/api/v1/admin/facilities/{$facility->id}"],
            ['putJson', "/api/v1/admin/facilities/{$facility->id}"],
            ['deleteJson', "/api/v1/admin/facilities/{$facility->id}"],
            ['getJson', "/api/v1/admin/facilities/{$facility->id}/spaces"],
            ['postJson', "/api/v1/admin/facilities/{$facility->id}/spaces"],
            ['getJson', "/api/v1/admin/spaces/{$space->id}"],
            ['putJson', "/api/v1/admin/spaces/{$space->id}"],
            ['deleteJson', "/api/v1/admin/spaces/{$space->id}"],
            ['getJson', '/api/v1/admin/bookable-units'],
            ['postJson', '/api/v1/admin/bookable-units'],
            ['getJson', "/api/v1/admin/bookable-units/{$unit->id}"],
            ['putJson', "/api/v1/admin/bookable-units/{$unit->id}"],
            ['deleteJson', "/api/v1/admin/bookable-units/{$unit->id}"],
            ['putJson', "/api/v1/admin/bookable-units/{$unit->id}/sports"],
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

    public function test_facility_crud_and_audit(): void
    {
        $this->actingAsFacilitiesManager();

        $response = $this->postJson('/api/v1/admin/facilities', [
            'type' => 'pitch',
            'name_ar' => 'الملعب الرئيسي',
            'sort_order' => 3,
        ])
            ->assertCreated()
            ->assertJsonPath('message', __('admin_facilities.facility_created', [], 'ar'))
            ->assertJsonPath('data.type', 'pitch')
            ->assertJsonPath('data.name_en', null)
            ->assertJsonPath('data.sort_order', 3)
            ->assertJsonPath('data.is_active', true);

        $id = $response->json('data.id');
        $this->assertDatabaseHas('facilities', ['id' => $id, 'name_ar' => 'الملعب الرئيسي']);
        $this->assertTrue(AuditLog::query()->where('action', 'facility.created')->exists());

        $this->getJson("/api/v1/admin/facilities/{$id}")->assertOk()->assertJsonPath('data.id', $id);

        $this->putJson("/api/v1/admin/facilities/{$id}", ['name_en' => 'Main pitch', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name_en', 'Main pitch')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.name_ar', 'الملعب الرئيسي');
        $this->assertTrue(AuditLog::query()->where('action', 'facility.updated')->exists());

        $this->deleteJson("/api/v1/admin/facilities/{$id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_facilities.facility_deleted', [], 'ar'));
        $this->assertDatabaseMissing('facilities', ['id' => $id]);
        $this->assertTrue(AuditLog::query()->where('action', 'facility.deleted')->exists());
    }

    public function test_facility_index_is_ordered_and_filtered_with_nested_data(): void
    {
        $this->actingAsFacilitiesManager();

        $second = Facility::factory()->create(['type' => 'hall', 'sort_order' => 2]);
        $first = Facility::factory()->create(['type' => 'pitch', 'sort_order' => 1]);
        Facility::factory()->create(['type' => 'pitch', 'sort_order' => 3, 'is_active' => false]);

        $space = Space::factory()->for($first)->create();
        $unit = BookableUnit::factory()->for($first)->create();
        $unit->spaces()->attach($space);
        $unit->sports()->attach(Sport::factory()->create());

        $this->getJson('/api/v1/admin/facilities')
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.0.spaces.0.id', $space->id)
            ->assertJsonPath('data.0.units.0.spaces.0.id', $space->id)
            ->assertJsonCount(1, 'data.0.units.0.sports');

        $this->getJson('/api/v1/admin/facilities?type=hall')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/facilities?is_active=0')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/facilities?type=pitch&is_active=1')->assertJsonCount(1, 'data');
    }

    public function test_facility_validation_errors_in_both_languages(): void
    {
        $this->actingAsFacilitiesManager();

        $this->postJson('/api/v1/admin/facilities', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.type.0', __('admin_facilities.validation.type_invalid', [], 'ar'))
            ->assertJsonPath('errors.name_ar.0', __('admin_facilities.validation.name_ar_required', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/facilities', [
                'type' => 'stadium',
                'name_ar' => ['x'],
                'name_en' => str_repeat('a', 101),
                'description_ar' => str_repeat('a', 5001),
                'description_en' => 5,
                'sort_order' => 'abc',
                'is_active' => 'maybe',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.type.0', 'The facility type must be either pitch or hall.')
            ->assertJsonPath('errors.name_ar.0', 'The Arabic name must be text of at most 100 characters.')
            ->assertJsonPath('errors.name_en.0', 'The English name must be text of at most 100 characters.')
            ->assertJsonPath('errors.description_ar.0', 'The Arabic description must be text of at most 5000 characters.')
            ->assertJsonPath('errors.description_en.0', 'The English description must be text of at most 5000 characters.')
            ->assertJsonPath('errors.sort_order.0', 'The sort order must be a whole number between 0 and 65535.')
            ->assertJsonPath('errors.is_active.0', 'The active flag must be true or false.');

        $facility = Facility::factory()->create();
        $this->withHeader('Accept-Language', 'en')
            ->putJson("/api/v1/admin/facilities/{$facility->id}", ['sort_order' => 70000, 'name_ar' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.sort_order.0', 'The sort order must be a whole number between 0 and 65535.')
            ->assertJsonPath('errors.name_ar.0', 'The Arabic name is required.');
    }

    public function test_missing_ids_return_not_found(): void
    {
        $this->actingAsFacilitiesManager();

        $this->getJson('/api/v1/admin/facilities/9999')->assertNotFound();
        $this->putJson('/api/v1/admin/facilities/9999', [])->assertNotFound();
        $this->deleteJson('/api/v1/admin/facilities/9999')->assertNotFound();
        $this->getJson('/api/v1/admin/facilities/9999/spaces')->assertNotFound();
        $this->postJson('/api/v1/admin/facilities/9999/spaces', ['code' => 'A', 'name_ar' => 'ب'])->assertNotFound();
        $this->getJson('/api/v1/admin/spaces/9999')->assertNotFound();
        $this->deleteJson('/api/v1/admin/spaces/9999')->assertNotFound();
        $this->getJson('/api/v1/admin/bookable-units/9999')->assertNotFound();
        $this->putJson('/api/v1/admin/bookable-units/9999/sports', ['sport_ids' => []])->assertNotFound();
        $this->deleteJson('/api/v1/admin/bookable-units/9999')->assertNotFound();
    }

    public function test_deleting_facility_with_spaces_returns_conflict(): void
    {
        $this->actingAsFacilitiesManager();
        $facility = Facility::factory()->create();
        Space::factory()->for($facility)->create();

        $this->deleteJson("/api/v1/admin/facilities/{$facility->id}")
            ->assertConflict()
            ->assertJsonPath('message', __('admin_facilities.facility_in_use', [], 'ar'));

        $this->assertDatabaseHas('facilities', ['id' => $facility->id]);
        $this->assertFalse(AuditLog::query()->where('action', 'facility.deleted')->exists());
    }

    public function test_space_crud_and_nested_listing(): void
    {
        $this->actingAsFacilitiesManager();
        $facility = Facility::factory()->create();
        $other = Facility::factory()->create();
        Space::factory()->for($other)->create();

        $response = $this->postJson("/api/v1/admin/facilities/{$facility->id}/spaces", ['code' => 'HALF_1', 'name_ar' => 'نصف أول'])
            ->assertCreated()
            ->assertJsonPath('message', __('admin_facilities.space_created', [], 'ar'))
            ->assertJsonPath('data.facility_id', $facility->id)
            ->assertJsonPath('data.code', 'HALF_1')
            ->assertJsonPath('data.name_en', null);
        $id = $response->json('data.id');

        $this->getJson("/api/v1/admin/facilities/{$facility->id}/spaces")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/admin/spaces/{$id}")->assertOk()->assertJsonPath('data.code', 'HALF_1');

        $this->putJson("/api/v1/admin/spaces/{$id}", ['code' => 'HALF_1', 'name_en' => 'First half'])
            ->assertOk()
            ->assertJsonPath('data.name_en', 'First half');

        $this->deleteJson("/api/v1/admin/spaces/{$id}")->assertOk();
        $this->assertDatabaseMissing('spaces', ['id' => $id]);

        foreach (['space.created', 'space.updated', 'space.deleted'] as $action) {
            $this->assertTrue(AuditLog::query()->where('action', $action)->exists(), $action);
        }
    }

    public function test_space_code_uniqueness_is_scoped_to_the_facility(): void
    {
        $this->actingAsFacilitiesManager();
        $facility = Facility::factory()->create();
        $other = Facility::factory()->create();
        $space = Space::factory()->for($facility)->create(['code' => 'S1']);
        $sibling = Space::factory()->for($facility)->create(['code' => 'S2']);

        $this->postJson("/api/v1/admin/facilities/{$facility->id}/spaces", ['code' => 'S1', 'name_ar' => 'م'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', __('admin_facilities.validation.space_code_taken', [], 'ar'));

        $this->postJson("/api/v1/admin/facilities/{$other->id}/spaces", ['code' => 'S1', 'name_ar' => 'م'])->assertCreated();

        $this->putJson("/api/v1/admin/spaces/{$space->id}", ['code' => 'S1'])->assertOk();
        $this->putJson("/api/v1/admin/spaces/{$sibling->id}", ['code' => 'S1'])->assertUnprocessable();
    }

    public function test_space_validation_errors_in_both_languages(): void
    {
        $this->actingAsFacilitiesManager();
        $facility = Facility::factory()->create();

        $this->postJson("/api/v1/admin/facilities/{$facility->id}/spaces", [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', __('admin_facilities.validation.space_code_invalid', [], 'ar'))
            ->assertJsonPath('errors.name_ar.0', __('admin_facilities.validation.name_ar_required', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson("/api/v1/admin/facilities/{$facility->id}/spaces", ['code' => 'bad code', 'name_ar' => 'م', 'name_en' => str_repeat('a', 101)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'The space code is required and must be at most 20 characters of capital letters, digits or underscores.')
            ->assertJsonPath('errors.name_en.0', 'The English name must be text of at most 100 characters.');

        $this->withHeader('Accept-Language', 'en')
            ->postJson("/api/v1/admin/facilities/{$facility->id}/spaces", ['code' => 123, 'name_ar' => ['x']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name_ar.0', 'The Arabic name must be text of at most 100 characters.');
    }

    public function test_deleting_space_linked_to_a_unit_returns_conflict(): void
    {
        $this->actingAsFacilitiesManager();
        $facility = Facility::factory()->create();
        $space = Space::factory()->for($facility)->create();
        BookableUnit::factory()->for($facility)->create()->spaces()->attach($space);

        $this->deleteJson("/api/v1/admin/spaces/{$space->id}")
            ->assertConflict()
            ->assertJsonPath('message', __('admin_facilities.space_in_use', [], 'ar'));

        $this->assertDatabaseHas('spaces', ['id' => $space->id]);
    }

    public function test_unit_is_created_with_spaces_and_sports(): void
    {
        $this->actingAsFacilitiesManager();
        $facility = Facility::factory()->create();
        $spaces = Space::factory()->count(2)->for($facility)->create();
        $sport = Sport::factory()->create();

        $response = $this->postJson('/api/v1/admin/bookable-units', $this->unitPayload($facility, $spaces->pluck('id')->all(), [
            'sport_ids' => [$sport->id],
            'sort_order' => 4,
        ]))
            ->assertCreated()
            ->assertJsonPath('message', __('admin_facilities.unit_created', [], 'ar'))
            ->assertJsonPath('data.code', 'PITCH_A')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.sort_order', 4)
            ->assertJsonCount(2, 'data.spaces')
            ->assertJsonPath('data.sports.0.id', $sport->id)
            ->assertJsonStructure(['data' => ['sports' => [['id', 'name_ar', 'name_en', 'icon_url', 'is_active']]]]);

        $unit = BookableUnit::query()->findOrFail($response->json('data.id'));
        $this->assertCount(2, $unit->spaces);
        $this->assertCount(1, $unit->sports);
        $this->assertTrue(AuditLog::query()->where('action', 'bookable_unit.created')->exists());
    }

    public function test_unit_index_filters_and_orders(): void
    {
        $this->actingAsFacilitiesManager();
        $facility = Facility::factory()->create();
        $other = Facility::factory()->create();
        $late = BookableUnit::factory()->for($facility)->create(['sort_order' => 2]);
        $early = BookableUnit::factory()->for($facility)->create(['sort_order' => 1]);
        BookableUnit::factory()->for($other)->inactive()->create();

        $this->getJson("/api/v1/admin/bookable-units?facility_id={$facility->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $early->id)
            ->assertJsonPath('data.1.id', $late->id);
        $this->getJson('/api/v1/admin/bookable-units?is_active=0')->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/admin/bookable-units/{$late->id}")->assertOk()->assertJsonPath('data.id', $late->id);
    }

    public function test_unit_rejects_space_from_another_facility(): void
    {
        $this->actingAsFacilitiesManager();
        $facility = Facility::factory()->create();
        $foreignSpace = Space::factory()->for(Facility::factory()->create())->create();

        $this->postJson('/api/v1/admin/bookable-units', $this->unitPayload($facility, [$foreignSpace->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.space_ids.0', __('admin_facilities.validation.space_ids_facility', [], 'ar'));

        $this->assertDatabaseCount('bookable_units', 0);
    }

    public function test_unit_validation_errors_in_both_languages(): void
    {
        $this->actingAsFacilitiesManager();
        $facility = Facility::factory()->create();
        $space = Space::factory()->for($facility)->create();
        BookableUnit::factory()->for($facility)->create(['code' => 'TAKEN']);

        $this->postJson('/api/v1/admin/bookable-units', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.facility_id.0', __('admin_facilities.validation.facility_id_invalid', [], 'ar'))
            ->assertJsonPath('errors.space_ids.0', __('admin_facilities.validation.space_ids_required', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/bookable-units', $this->unitPayload($facility, [$space->id], ['code' => 'TAKEN']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'This unit code is already used.');

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/bookable-units', $this->unitPayload($facility, [$space->id, $space->id], [
                'code' => 'lower',
                'name_ar' => '',
                'is_active' => 'x',
                'sort_order' => -1,
                'sport_ids' => [999],
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'The unit code is required and must be at most 20 characters of capital letters, digits or underscores.')
            ->assertJsonPath('errors.name_ar.0', 'The Arabic name is required.')
            ->assertJsonPath('errors.is_active.0', 'The active flag must be true or false.')
            ->assertJsonPath('errors.sort_order.0', 'The sort order must be a whole number between 0 and 65535.')
            ->assertJson(['errors' => ['sport_ids.0' => ['Each sport must be a valid sport id without duplicates.']]]);

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/bookable-units', $this->unitPayload($facility, 'x', ['sport_ids' => 'x']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.space_ids.0', 'The spaces must be a list of space ids.');
    }

    public function test_unit_update_changes_fields_and_spaces_with_audit(): void
    {
        $this->actingAsFacilitiesManager();
        $facility = Facility::factory()->create();
        $first = Space::factory()->for($facility)->create();
        $second = Space::factory()->for($facility)->create();
        $unit = BookableUnit::factory()->for($facility)->create(['code' => 'UNIT_1']);
        $unit->spaces()->attach($first);

        $this->putJson("/api/v1/admin/bookable-units/{$unit->id}", ['name_en' => 'Renamed', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name_en', 'Renamed')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonCount(1, 'data.spaces');
        $this->assertFalse(AuditLog::query()->where('action', 'bookable_unit.spaces_updated')->exists());

        $this->putJson("/api/v1/admin/bookable-units/{$unit->id}", ['space_ids' => [$second->id]])
            ->assertOk()
            ->assertJsonPath('data.spaces.0.id', $second->id);

        $log = AuditLog::query()->where('action', 'bookable_unit.spaces_updated')->sole();
        $this->assertSame(['space_ids' => [$first->id]], $log->before);
        $this->assertSame(['space_ids' => [$second->id]], $log->after);
        $this->assertTrue(AuditLog::query()->where('action', 'bookable_unit.updated')->exists());

        $this->putJson("/api/v1/admin/bookable-units/{$unit->id}", ['code' => $unit->code])->assertOk();
    }

    public function test_unit_update_validates_spaces_against_current_or_new_facility(): void
    {
        $this->actingAsFacilitiesManager();
        $facility = Facility::factory()->create();
        $other = Facility::factory()->create();
        $space = Space::factory()->for($facility)->create();
        $otherSpace = Space::factory()->for($other)->create();
        $unit = BookableUnit::factory()->for($facility)->create();
        $unit->spaces()->attach($space);

        $this->putJson("/api/v1/admin/bookable-units/{$unit->id}", ['space_ids' => [$otherSpace->id]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.space_ids.0', __('admin_facilities.validation.space_ids_facility', [], 'ar'));

        $this->putJson("/api/v1/admin/bookable-units/{$unit->id}", ['facility_id' => $other->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.space_ids.0', __('admin_facilities.validation.space_ids_required', [], 'ar'));

        $this->putJson("/api/v1/admin/bookable-units/{$unit->id}", ['facility_id' => $other->id, 'space_ids' => [$space->id]])
            ->assertUnprocessable();

        $this->putJson("/api/v1/admin/bookable-units/{$unit->id}", ['facility_id' => $other->id, 'space_ids' => [$otherSpace->id]])
            ->assertOk()
            ->assertJsonPath('data.facility_id', $other->id)
            ->assertJsonPath('data.spaces.0.id', $otherSpace->id);
    }

    public function test_duplicate_unit_code_is_rejected_on_update_but_own_code_is_allowed(): void
    {
        $this->actingAsFacilitiesManager();
        $facility = Facility::factory()->create();
        $a = BookableUnit::factory()->for($facility)->create(['code' => 'AAA']);
        BookableUnit::factory()->for($facility)->create(['code' => 'BBB']);

        $this->putJson("/api/v1/admin/bookable-units/{$a->id}", ['code' => 'BBB'])->assertUnprocessable();
        $this->putJson("/api/v1/admin/bookable-units/{$a->id}", ['code' => 'AAA'])->assertOk();
    }

    public function test_sync_sports_replaces_clears_and_audits_only_changes(): void
    {
        $this->actingAsFacilitiesManager();
        $unit = BookableUnit::factory()->create();
        $sports = Sport::factory()->count(2)->create();

        $this->putJson("/api/v1/admin/bookable-units/{$unit->id}/sports", ['sport_ids' => $sports->pluck('id')->all()])
            ->assertOk()
            ->assertJsonPath('message', __('admin_facilities.unit_sports_updated', [], 'ar'))
            ->assertJsonCount(2, 'data.sports');

        $log = AuditLog::query()->where('action', 'bookable_unit.sports_updated')->sole();
        $this->assertSame(['sport_ids' => []], $log->before);
        $this->assertSame(['sport_ids' => $sports->pluck('id')->sort()->values()->all()], $log->after);

        $this->putJson("/api/v1/admin/bookable-units/{$unit->id}/sports", ['sport_ids' => $sports->pluck('id')->all()])->assertOk();
        $this->assertSame(1, AuditLog::query()->where('action', 'bookable_unit.sports_updated')->count());

        $this->putJson("/api/v1/admin/bookable-units/{$unit->id}/sports", ['sport_ids' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data.sports');
        $this->assertDatabaseCount('unit_sports', 0);
        $this->assertSame(2, AuditLog::query()->where('action', 'bookable_unit.sports_updated')->count());
    }

    public function test_sync_sports_validation_errors_in_both_languages(): void
    {
        $this->actingAsFacilitiesManager();
        $unit = BookableUnit::factory()->create();

        $this->putJson("/api/v1/admin/bookable-units/{$unit->id}/sports", [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.sport_ids.0', __('admin_facilities.validation.sport_ids_required', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->putJson("/api/v1/admin/bookable-units/{$unit->id}/sports", ['sport_ids' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.sport_ids.0', 'The sports must be a list of sport ids.');

        $this->withHeader('Accept-Language', 'en')
            ->putJson("/api/v1/admin/bookable-units/{$unit->id}/sports", ['sport_ids' => [999]])
            ->assertUnprocessable()
            ->assertJson(['errors' => ['sport_ids.0' => ['Each sport must be a valid sport id without duplicates.']]]);
    }

    public function test_deleting_unused_unit_removes_pivots(): void
    {
        $this->actingAsFacilitiesManager();
        $facility = Facility::factory()->create();
        $space = Space::factory()->for($facility)->create();
        $unit = BookableUnit::factory()->for($facility)->create();
        $unit->spaces()->attach($space);
        $unit->sports()->attach(Sport::factory()->create());

        $this->deleteJson("/api/v1/admin/bookable-units/{$unit->id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_facilities.unit_deleted', [], 'ar'));

        $this->assertDatabaseMissing('bookable_units', ['id' => $unit->id]);
        $this->assertDatabaseCount('unit_spaces', 0);
        $this->assertDatabaseCount('unit_sports', 0);
        $this->assertDatabaseHas('spaces', ['id' => $space->id]);
        $this->assertTrue(AuditLog::query()->where('action', 'bookable_unit.deleted')->exists());
    }

    public function test_deleting_unit_with_price_rule_returns_conflict_and_keeps_pivots(): void
    {
        $this->actingAsFacilitiesManager();
        $facility = Facility::factory()->create();
        $space = Space::factory()->for($facility)->create();
        $unit = BookableUnit::factory()->for($facility)->create();
        $unit->spaces()->attach($space);
        $unit->sports()->attach(Sport::factory()->create());
        PriceRule::factory()->create(['unit_id' => $unit->id]);

        $this->deleteJson("/api/v1/admin/bookable-units/{$unit->id}")
            ->assertConflict()
            ->assertJsonPath('message', __('admin_facilities.unit_in_use', [], 'ar'));

        $this->assertDatabaseHas('bookable_units', ['id' => $unit->id]);
        $this->assertDatabaseCount('unit_spaces', 1);
        $this->assertDatabaseCount('unit_sports', 1);
        $this->assertFalse(AuditLog::query()->where('action', 'bookable_unit.deleted')->exists());
    }
}
